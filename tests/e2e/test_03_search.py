"""完了条件 3: タスク名・キーワード・タグの全文検索。"""
from .conftest import listed_titles, unique


def test_search_by_task_name(anon, make_post):
    token = unique("zq")
    hit = make_post(title=f"Dishwasher loading {token}")
    miss = make_post(title=unique("unrelated-"))

    text = listed_titles(anon, f"keys={token}")

    assert hit["title"] in text
    assert miss["title"] not in text


def test_search_by_keyword_in_procedure(anon, make_post):
    token = unique("kw")
    hit = make_post(title=unique("proc-"), procedure=f"## Steps\n\nCalibrate the gripper with {token} first.")

    assert hit["title"] in listed_titles(anon, f"keys={token}")


def test_search_by_tag_name(anon, make_post):
    hit = make_post(title=unique("tagged-"), tags=["Diffusion Policy"])
    miss = make_post(title=unique("untagged-"), tags=["MoveIt"])

    text = listed_titles(anon, "keys=Diffusion")

    assert hit["title"] in text
    assert miss["title"] not in text


def test_search_japanese_partial_match(anon, make_post):
    token = unique("")
    hit = make_post(title=f"柔軟物把持の失敗事例{token}")

    assert hit["title"] in listed_titles(anon, f"keys=事例{token}")


def test_search_combines_with_filters(anon, make_post):
    token = unique("cmb")
    ok = make_post(title=f"{token} success", outcome="success")
    ng = make_post(title=f"{token} failure", outcome="failure")

    text = listed_titles(anon, f"keys={token}&outcome=failure")

    assert ng["title"] in text
    assert ok["title"] not in text
