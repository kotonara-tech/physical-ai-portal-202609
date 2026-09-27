"""完了条件 2: 動画・軌道・YAML の登録／表示／ダウンロード。"""
import pytest

from .conftest import (HDF5_BYTES, MP4_BYTES, PARQUET_BYTES, VALID_METADATA_YAML,
                       build_post_payload, post_node, soup_of, unique, url)


@pytest.fixture
def post_with_files(upload, make_post):
    token = unique("f")
    files = {
        "field_video": upload("field_video", f"{token}.mp4", MP4_BYTES),
        "field_trajectory": upload("field_trajectory", f"{token}.parquet", PARQUET_BYTES),
        "field_metadata_yaml": upload("field_metadata_yaml", f"{token}.yaml", VALID_METADATA_YAML.encode()),
    }
    post = make_post(title=unique("files-"), files={field: f["uuid"] for field, f in files.items()})
    return post, files


def test_video_is_previewed_on_detail_page(anon, post_with_files):
    post, files = post_with_files
    page = soup_of(anon.get(url(f"/node/{post['nid']}")))
    sources = [tag.get("src", "") for tag in page.select("video source, video[src]")]
    assert any(files["field_video"]["url"] in src for src in sources), sources


def test_trajectory_and_yaml_have_download_links(anon, post_with_files):
    post, files = post_with_files
    page = soup_of(anon.get(url(f"/node/{post['nid']}")))
    hrefs = [a["href"] for a in page.select("a[href]")]
    for field in ("field_trajectory", "field_metadata_yaml"):
        assert any(files[field]["url"] in href for href in hrefs), f"{field} の DL リンクが無い"


@pytest.mark.parametrize("field,expected", [
    ("field_video", MP4_BYTES),
    ("field_trajectory", PARQUET_BYTES),
    ("field_metadata_yaml", VALID_METADATA_YAML.encode()),
], ids=["video", "trajectory", "yaml"])
def test_files_download_byte_identical(anon, post_with_files, field, expected):
    _, files = post_with_files
    response = anon.get(url(files[field]["url"]))
    assert response.status_code == 200
    assert response.content == expected


def test_yaml_phases_are_shown_on_detail_page(anon, post_with_files):
    post, _ = post_with_files
    block = soup_of(anon.get(url(f"/node/{post['nid']}"))).select_one(".soarm-metadata")
    assert block is not None, ".soarm-metadata が無い"
    text = block.get_text(" ")
    for phase in ("grasp", "move", "release"):
        assert phase in text


def test_hdf5_trajectory_is_accepted(upload, make_post):
    f = upload("field_trajectory", f"{unique('t')}.hdf5", HDF5_BYTES)
    make_post(files={"field_trajectory": f["uuid"]})


def test_trajectory_with_wrong_magic_bytes_is_rejected(api, terms, upload):
    f = upload("field_trajectory", f"{unique('bad')}.parquet", b"this is not parquet")
    response = post_node(api, build_post_payload(terms, title=unique("bad-"), files={"field_trajectory": f["uuid"]}))
    assert response.status_code == 422, response.text[:300]


def test_yaml_without_grasp_move_release_is_rejected(api, terms, upload):
    f = upload("field_metadata_yaml", f"{unique('bad')}.yaml", b"robot_type: so-arm100\nfps: 30\n")
    response = post_node(api, build_post_payload(terms, title=unique("bad-"), files={"field_metadata_yaml": f["uuid"]}))
    assert response.status_code == 422, response.text[:300]


def test_lerobot_endpoint_exposes_metadata(anon, post_with_files):
    post, files = post_with_files
    response = anon.get(url(f"/api/soarm/lerobot/{post['nid']}"))
    assert response.status_code == 200
    info = response.json()
    assert info["robot_type"] == "so-arm101"
    assert info["fps"] == 30
    assert [p["name"] for p in info["phases"]] == ["grasp", "move", "release"]
    assert files["field_trajectory"]["url"] in info["data_path"]
    assert files["field_video"]["url"] in info["video_path"]
