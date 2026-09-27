"""完了条件 5: JSON:API で取得・登録が可能。"""
import pytest
import requests

from .conftest import JSONAPI, build_post_payload, drush, post_node, unique, url


def test_collection_is_readable_anonymously(anon, make_post):
    post = make_post()
    response = anon.get(url(f"/jsonapi/node/robot_knowledge?filter[title]={post['title']}"), headers={"Accept": JSONAPI})
    assert response.status_code == 200
    assert [item["id"] for item in response.json()["data"]] == [post["uuid"]]


def test_created_post_round_trips_field_values(api, make_post):
    post = make_post(outcome="partial", resolved=True, procedure="## Steps\n\n1. grasp",
                     extra_attributes={"field_environment": "desk, 500lx", "field_hf_repo": "lerobot/so101_pick"})
    attributes = api.get(url(f"/jsonapi/node/robot_knowledge/{post['uuid']}")).json()["data"]["attributes"]
    assert attributes["field_outcome"] == "partial"
    assert attributes["field_resolved"] is True
    assert attributes["field_environment"] == "desk, 500lx"
    assert attributes["field_hf_repo"] == "lerobot/so101_pick"
    assert attributes["field_procedure"]["value"].startswith("## Steps")


def test_anonymous_cannot_create(anon, terms):
    response = post_node(anon, build_post_payload(terms, title=unique("anon-")))
    assert response.status_code in (401, 403)


def test_invalid_outcome_is_rejected(api, terms):
    response = post_node(api, build_post_payload(terms, title=unique("bad-"), outcome="maybe"))
    assert response.status_code == 422


@pytest.fixture
def contributor():
    name = unique("contrib")
    drush("user:create", name, "--password=e2e-pass")
    drush("user:role:add", "soarm_contributor", name)
    session = requests.Session()
    session.auth = (name, "e2e-pass")
    session.headers.update({"Accept": JSONAPI})
    yield session
    drush("user:cancel", "--delete-content", name, "-y")


def test_contributor_role_can_create_via_jsonapi(contributor, terms):
    """ROS2 などの外部クライアントは管理者ではなく投稿者ロールで登録する。"""
    response = post_node(contributor, build_post_payload(terms, title=unique("ros2-")))
    assert response.status_code == 201, response.text[:500]


def test_markdown_procedure_is_rendered_safely(anon, make_post):
    post = make_post(procedure="## Steps\n\n1. grasp\n\n<script>alert(1)</script>")
    html = anon.get(url(f"/node/{post['nid']}")).text
    assert "<h2>Steps</h2>" in html
    assert "<script>alert(1)" not in html
