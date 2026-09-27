"""E2E 受け入れテストの共通部品。

`docker compose up -d` で起動したスタックに HTTP で当てる。
完了条件（miro-markdown.md）を外から見える振る舞いとして固定するのが目的。

  SOARM_BASE_URL    既定 http://localhost:8080
  SOARM_ADMIN_USER  既定 admin
  SOARM_ADMIN_PASS  既定 admin
"""
from __future__ import annotations

import os
import subprocess
import uuid
from pathlib import Path

import pytest
import requests
from bs4 import BeautifulSoup

BASE_URL = os.environ.get("SOARM_BASE_URL", "http://localhost:8080").rstrip("/")
ADMIN = (os.environ.get("SOARM_ADMIN_USER", "admin"), os.environ.get("SOARM_ADMIN_PASS", "admin"))
DRUPAL_CONTAINER = os.environ.get("SOARM_DRUPAL_CONTAINER", "workspace-drupal-1")
REPO_ROOT = Path(__file__).resolve().parents[2]
JSONAPI = "application/vnd.api+json"

CATEGORIES = ["家庭内作業", "製造・組立", "物流・搬送", "研究・教育", "データ収集・学習"]
DIFFICULTIES = ["初級", "中級", "上級"]
ROBOT_MODELS = ["SO-ARM100", "SO-ARM101"]

# --- 最小のサンプルファイル（マジックバイトだけ正しい） ----------------------
MP4_BYTES = b"\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" + b"\x00" * 64
PARQUET_BYTES = b"PAR1" + b"\x00" * 32 + b"PAR1"
HDF5_BYTES = b"\x89HDF\r\n\x1a\n" + b"\x00" * 64
VALID_METADATA_YAML = """\
robot_type: so-arm101
fps: 30
task: pick a block and place it in the tray
phases:
  - name: grasp
    start_frame: 0
    end_frame: 40
  - name: move
    start_frame: 41
    end_frame: 120
  - name: release
    start_frame: 121
    end_frame: 150
"""


def url(path: str) -> str:
    return path if path.startswith("http") else f"{BASE_URL}{path}"


def unique(prefix: str = "e2e") -> str:
    return f"{prefix}{uuid.uuid4().hex[:10]}"


def soup_of(response: requests.Response) -> BeautifulSoup:
    return BeautifulSoup(response.text, "html.parser")


def drush(*args: str) -> str:
    result = subprocess.run(
        ["docker", "exec", "-u", "www-data", DRUPAL_CONTAINER, "drush", *args],
        capture_output=True, text=True, check=True,
    )
    return result.stdout.strip()


@pytest.fixture(scope="session")
def anon() -> requests.Session:
    session = requests.Session()
    try:
        session.get(url("/"), timeout=10)
    except requests.ConnectionError:
        pytest.exit(f"{BASE_URL} に接続できません。先に `docker compose up -d` を実行してください。", 2)
    return session


@pytest.fixture(scope="session")
def api(anon) -> requests.Session:
    """管理者として JSON:API を叩くセッション（Basic 認証）。"""
    session = requests.Session()
    session.auth = ADMIN
    session.headers.update({"Accept": JSONAPI})
    return session


@pytest.fixture(scope="session")
def terms(api):
    """terms('task_category') -> {名前: {'uuid':…, 'tid':…}}"""
    cache: dict[str, dict] = {}

    def _terms(vocabulary: str) -> dict:
        if vocabulary not in cache:
            response = api.get(url(f"/jsonapi/taxonomy_term/{vocabulary}?page[limit]=50"))
            assert response.status_code == 200, f"語彙 {vocabulary} を取得できない: {response.status_code}"
            cache[vocabulary] = {
                item["attributes"]["name"]: {
                    "uuid": item["id"],
                    "tid": item["attributes"]["drupal_internal__tid"],
                }
                for item in response.json()["data"]
            }
        return cache[vocabulary]

    return _terms


@pytest.fixture(scope="session")
def upload(api):
    """upload('field_video', 'a.mp4', bytes) -> {'uuid':…, 'url':…}"""

    def _upload(field: str, filename: str, content: bytes) -> dict:
        response = api.post(
            url(f"/jsonapi/node/robot_knowledge/{field}"),
            data=content,
            headers={
                "Content-Type": "application/octet-stream",
                "Content-Disposition": f'file; filename="{filename}"',
            },
        )
        assert response.status_code == 201, f"{field} へのアップロード失敗: {response.status_code} {response.text[:300]}"
        data = response.json()["data"]
        return {"uuid": data["id"], "url": data["attributes"]["uri"]["url"]}

    return _upload


def build_post_payload(terms, *, title, category=CATEGORIES[0], difficulty=DIFFICULTIES[0],
                       models=("SO-ARM100",), outcome="success", resolved=False,
                       procedure="", tags=(), files=None, extra_attributes=None) -> dict:
    relationships = {
        "field_task_category": {"data": {"type": "taxonomy_term--task_category", "id": terms("task_category")[category]["uuid"]}},
        "field_difficulty": {"data": {"type": "taxonomy_term--difficulty", "id": terms("difficulty")[difficulty]["uuid"]}},
        "field_robot_model": {"data": [
            {"type": "taxonomy_term--robot_model", "id": terms("robot_model")[name]["uuid"]} for name in models
        ]},
    }
    if tags:
        relationships["field_tech_tags"] = {"data": [
            {"type": "taxonomy_term--tech_tags", "id": terms("tech_tags")[name]["uuid"]} for name in tags
        ]}
    for field, file_uuid in (files or {}).items():
        relationships[field] = {"data": {"type": "file--file", "id": file_uuid}}
    attributes = {"title": title, "field_outcome": outcome, "field_resolved": resolved}
    if procedure:
        attributes["field_procedure"] = {"value": procedure, "format": "soarm_markdown"}
    attributes.update(extra_attributes or {})
    return {"data": {"type": "node--robot_knowledge", "attributes": attributes, "relationships": relationships}}


def post_node(session, payload) -> requests.Response:
    return session.post(url("/jsonapi/node/robot_knowledge"), json=payload, headers={"Content-Type": JSONAPI})


@pytest.fixture
def make_post(api, terms):
    """ロボット知見投稿を JSON:API で作る。テスト後に消す。"""
    created: list[str] = []

    def _make(**kwargs) -> dict:
        kwargs.setdefault("title", unique("E2E post "))
        response = post_node(api, build_post_payload(terms, **kwargs))
        assert response.status_code == 201, f"投稿の作成に失敗: {response.status_code} {response.text[:500]}"
        data = response.json()["data"]
        created.append(data["id"])
        return {"uuid": data["id"], "nid": data["attributes"]["drupal_internal__nid"], "title": kwargs["title"]}

    yield _make
    for node_uuid in created:
        api.delete(url(f"/jsonapi/node/robot_knowledge/{node_uuid}"))


def listed_titles(session, query: str) -> str:
    """一覧ページ /knowledge の結果部分のテキスト。"""
    response = session.get(url(f"/knowledge?{query}"))
    assert response.status_code == 200, f"/knowledge?{query} -> {response.status_code}"
    view = soup_of(response).select_one(".view-id-soarm_knowledge")
    assert view is not None, "一覧ビュー (.view-id-soarm_knowledge) が無い"
    return view.get_text(" ")
