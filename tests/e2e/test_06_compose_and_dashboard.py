"""完了条件 6: Docker Compose 一発起動。あわせてダッシュボードとトップの到達性。"""
import json
import subprocess

from .conftest import REPO_ROOT, soup_of, unique, url


def test_compose_config_is_valid_without_env_file():
    """.env が無くても `docker compose up -d` できる（必須の環境変数が無い）。"""
    result = subprocess.run(["docker", "compose", "config", "--format", "json"],
                            cwd=REPO_ROOT, capture_output=True, text=True)
    assert result.returncode == 0, result.stderr
    services = json.loads(result.stdout)["services"]
    assert {"nginx", "drupal", "mysql"} <= set(services)
    assert "ports" not in services["mysql"], "DB ポートはホストに公開しない"


def test_front_page_is_served_by_drupal(anon):
    response = anon.get(url("/"))
    assert response.status_code == 200
    assert response.headers.get("X-Generator", "").startswith("Drupal")


def test_soarm_modules_are_enabled_by_entrypoint():
    from .conftest import drush
    enabled = drush("pm:list", "--status=enabled", "--type=module", "--field=name")
    for module in ("soarm_core", "soarm_lerobot", "soarm_vote", "soarm_search", "soarm_dashboard", "soarm_api"):
        assert module in enabled.split(), f"{module} が有効になっていない"


def test_dashboard_has_three_sections(anon):
    page = soup_of(anon.get(url("/dashboard")))
    for section in ("soarm-popular", "soarm-latest", "soarm-unresolved"):
        assert page.select_one(f"#{section}") is not None, f"#{section} が無い"


def test_dashboard_latest_and_unresolved(anon, make_post):
    solved = make_post(title=unique("solved-"), outcome="failure", resolved=True)
    open_issue = make_post(title=unique("open-"), outcome="failure", resolved=False)
    success = make_post(title=unique("fine-"), outcome="success")

    page = soup_of(anon.get(url("/dashboard")))
    latest = page.select_one("#soarm-latest").get_text(" ")
    unresolved = page.select_one("#soarm-unresolved").get_text(" ")

    assert success["title"] in latest
    assert open_issue["title"] in unresolved
    assert solved["title"] not in unresolved
    assert success["title"] not in unresolved


def test_dashboard_popular_ranks_voted_post(api, anon, make_post):
    voted = make_post(title=unique("popular-"))
    api.post(url(f"/api/soarm/vote/{voted['nid']}"), json={"type": "useful"})
    popular = soup_of(anon.get(url("/dashboard"))).select_one("#soarm-popular").get_text(" ")
    assert voted["title"] in popular
