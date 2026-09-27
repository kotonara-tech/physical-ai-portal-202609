"""完了条件 1: 5 カテゴリで一覧フィルタが動作する。"""
import pytest

from .conftest import CATEGORIES, DIFFICULTIES, ROBOT_MODELS, listed_titles, unique


def test_five_categories_exist(terms):
    assert sorted(terms("task_category")) == sorted(CATEGORIES)


def test_difficulty_and_robot_model_vocabularies(terms):
    assert sorted(terms("difficulty")) == sorted(DIFFICULTIES)
    assert sorted(terms("robot_model")) == sorted(ROBOT_MODELS)


@pytest.mark.parametrize("category", CATEGORIES)
def test_filter_by_category(anon, terms, make_post, category):
    other = CATEGORIES[(CATEGORIES.index(category) + 1) % len(CATEGORIES)]
    mine = make_post(title=unique("cat-in-"), category=category)
    theirs = make_post(title=unique("cat-out-"), category=other)

    text = listed_titles(anon, f"category={terms('task_category')[category]['tid']}")

    assert mine["title"] in text
    assert theirs["title"] not in text


def test_filter_by_difficulty(anon, terms, make_post):
    easy = make_post(title=unique("easy-"), difficulty="初級")
    hard = make_post(title=unique("hard-"), difficulty="上級")

    text = listed_titles(anon, f"difficulty={terms('difficulty')['上級']['tid']}")

    assert hard["title"] in text
    assert easy["title"] not in text


def test_filter_by_outcome(anon, make_post):
    ok = make_post(title=unique("ok-"), outcome="success")
    ng = make_post(title=unique("ng-"), outcome="failure")

    text = listed_titles(anon, "outcome=failure")

    assert ng["title"] in text
    assert ok["title"] not in text


def test_filter_by_robot_model_includes_posts_supporting_both(anon, terms, make_post):
    """100 で絞ると「両対応」の投稿も出る。101 専用は出ない。"""
    only100 = make_post(title=unique("m100-"), models=["SO-ARM100"])
    only101 = make_post(title=unique("m101-"), models=["SO-ARM101"])
    both = make_post(title=unique("mboth-"), models=["SO-ARM100", "SO-ARM101"])

    text = listed_titles(anon, f"model={terms('robot_model')['SO-ARM100']['tid']}")

    assert only100["title"] in text
    assert both["title"] in text
    assert only101["title"] not in text
