"""完了条件 4: コメントと投票（有用性／改善提案／追試）。"""
import pytest

from .conftest import JSONAPI, soup_of, unique, url

VOTE_TYPES = ["useful", "improvement", "replication"]


def test_comment_can_be_posted_and_is_shown(api, anon, make_post):
    post = make_post()
    body = unique("comment-body-")
    response = api.post(url("/jsonapi/comment/knowledge_comment"), headers={"Content-Type": JSONAPI}, json={"data": {
        "type": "comment--knowledge_comment",
        "attributes": {
            "subject": "E2E", "entity_type": "node", "field_name": "field_comments",
            "comment_body": {"value": body, "format": "plain_text"},
        },
        "relationships": {"entity_id": {"data": {"type": "node--robot_knowledge", "id": post["uuid"]}}},
    }})
    assert response.status_code == 201, response.text[:500]

    assert body in anon.get(url(f"/node/{post['nid']}")).text


def test_votes_start_at_zero(anon, make_post):
    post = make_post()
    response = anon.get(url(f"/api/soarm/vote/{post['nid']}"))
    assert response.status_code == 200
    assert response.json()["counts"] == {t: 0 for t in VOTE_TYPES}


@pytest.mark.parametrize("vote_type", VOTE_TYPES)
def test_vote_is_counted(api, anon, make_post, vote_type):
    post = make_post()
    response = api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": vote_type})
    assert response.status_code in (200, 201), response.text[:300]

    counts = anon.get(url(f"/api/soarm/vote/{post['nid']}")).json()["counts"]
    assert counts == {t: (1 if t == vote_type else 0) for t in VOTE_TYPES}


def test_same_user_voting_twice_counts_once(api, make_post):
    post = make_post()
    api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "useful"})
    response = api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "useful"})
    assert response.json()["counts"]["useful"] == 1


def test_vote_can_be_withdrawn(api, make_post):
    post = make_post()
    api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "replication"})
    response = api.delete(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "replication"})
    assert response.status_code == 200
    assert response.json()["counts"]["replication"] == 0


def test_unknown_vote_type_is_rejected(api, make_post):
    post = make_post()
    assert api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "spam"}).status_code == 400


def test_anonymous_cannot_vote(anon, make_post):
    post = make_post()
    assert anon.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "useful"}).status_code in (401, 403)


def test_vote_counts_are_shown_on_detail_page(api, anon, make_post):
    post = make_post()
    api.post(url(f"/api/soarm/vote/{post['nid']}"), json={"type": "improvement"})
    page = soup_of(anon.get(url(f"/node/{post['nid']}")))
    for vote_type in VOTE_TYPES:
        element = page.select_one(f'[data-soarm-vote="{vote_type}"] [data-soarm-vote-count]')
        assert element is not None, f"{vote_type} の投票表示が無い"
        assert element.get_text(strip=True) == ("1" if vote_type == "improvement" else "0")
