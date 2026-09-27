"""完了条件 7: 将来拡張のコード内拡張ポイントがコメントで明示されている。"""
import re

import pytest

from .conftest import REPO_ROOT

CUSTOM = REPO_ROOT / "web" / "modules" / "custom"


def _extension_point_comments() -> list[str]:
    comments = []
    for path in list(CUSTOM.rglob("*.php")) + list(CUSTOM.rglob("*.module")) + list(CUSTOM.rglob("*.js")):
        for line in path.read_text(encoding="utf-8").splitlines():
            if re.search(r"^\s*(//|\*|/\*)", line) and "EXTENSION POINT" in line:
                comments.append(line)
    return comments


@pytest.mark.parametrize("topic", ["ROS2 bag", "Isaac Sim", "WebSocket/SSE"])
def test_extension_point_is_marked(topic):
    matching = [c for c in _extension_point_comments() if topic in c]
    assert matching, f"`EXTENSION POINT` コメントに {topic} が無い"
