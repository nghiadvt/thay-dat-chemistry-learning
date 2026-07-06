#!/usr/bin/env python3
"""Cursor hook: remind AI to update living docs when code changes."""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

# First matching rule wins (most specific first).
DOC_RULES: list[tuple[re.Pattern[str], list[str]]] = [
    (re.compile(r"^php-admin/database/migrations/"), ["docs/DATA_MODEL.md"]),
    (re.compile(r"^ws-server/"), ["docs/API_CONTRACTS.md"]),
    (re.compile(r"^php-admin/"), ["docs/DATA_MODEL.md", "docs/API_CONTRACTS.md"]),
    (
        re.compile(r"^prototype/js/(api|socket|game-adapter|backend-bridge|config)\.js$"),
        ["docs/APP_LOGIC.md", "docs/API_CONTRACTS.md"],
    ),
    (re.compile(r"^prototype/js/.*\.js$"), ["docs/APP_LOGIC.md"]),
    (
        re.compile(r"^prototype/.*\.(css|html)$"),
        ["docs/APP_STYLE.md", "docs/APP_LOGIC.md"],
    ),
]

SKIP_PREFIXES = (
    "docs/",
    ".cursor/",
    "node_modules/",
    "vendor/",
)

# Paths outside living-docs scope (assets, Laravel scaffold, mockups).
SKIP_PATH_PATTERNS: list[re.Pattern[str]] = [
    re.compile(r"\.(png|jpe?g|gif|webp|ico|svg)$", re.I),
    re.compile(r"^prototype/reference/"),
    re.compile(r"^prototype/assets/"),
    re.compile(r"^prototype/[^/]+\.(png|jpe?g|gif|webp)$", re.I),
    re.compile(r"^php-admin/resources/"),
    re.compile(r"^php-admin/vite\.config\.js$"),
    re.compile(r"^php-admin/package\.json$"),
]

SKIP_FILES = {
    "README.md",
    "ke-hoach-trien-khai-local.md",
    "local-deployment-plan.md",
    "dac-ta-ky-thuat-v4.docx.md",
}


def normalize_path(raw: str) -> str:
    path = raw.strip().replace("\\", "/")
    if path.startswith(str(ROOT) + "/"):
        path = path[len(str(ROOT)) + 1 :]
    return path.lstrip("./")


def should_skip_path(path: str) -> bool:
    if not path or path in SKIP_FILES:
        return True
    if any(path.startswith(prefix) for prefix in SKIP_PREFIXES):
        return True
    return any(pattern.search(path) for pattern in SKIP_PATH_PATTERNS)


def expected_docs(path: str) -> list[str]:
    if should_skip_path(path):
        return []

    for pattern, targets in DOC_RULES:
        if pattern.search(path):
            return list(targets)
    return []


def extract_edited_path(payload: dict) -> str:
    candidates: list[str] = []

    for key in ("file_path", "path"):
        value = payload.get(key)
        if isinstance(value, str):
            candidates.append(value)

    tool_input = payload.get("tool_input")
    if isinstance(tool_input, dict):
        for key in ("path", "file_path", "target_file", "target_notebook"):
            value = tool_input.get(key)
            if isinstance(value, str):
                candidates.append(value)

    for candidate in candidates:
        normalized = normalize_path(candidate)
        if normalized:
            return normalized
    return ""


def reminder_message(path: str, docs: list[str]) -> str:
    doc_list = ", ".join(f"`{doc}`" for doc in docs)
    return (
        f"Living-docs reminder: `{path}` was edited. "
        f"If this change affects schema, API/events, UI flow, or design tokens, "
        f"update {doc_list} in the same pass and refresh the "
        f"`Cập nhật lần cuối:` line."
    )


def emit(payload: dict) -> None:
    print(json.dumps(payload, ensure_ascii=False))


def handle_post_tool() -> int:
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError:
        emit({})
        return 0

    path = extract_edited_path(data)
    docs = expected_docs(path)
    if not docs:
        emit({})
        return 0

    emit({"additional_context": reminder_message(path, docs)})
    return 0


def git_changed_files() -> set[str]:
    files: set[str] = set()

    for args in (
        ["git", "diff", "--name-only", "HEAD"],
        ["git", "diff", "--cached", "--name-only"],
        ["git", "ls-files", "--others", "--exclude-standard"],
    ):
        try:
            result = subprocess.run(
                args,
                cwd=ROOT,
                capture_output=True,
                text=True,
                check=False,
            )
        except OSError:
            continue

        for line in result.stdout.splitlines():
            normalized = normalize_path(line)
            if normalized:
                files.add(normalized)

    return files


def handle_stop() -> int:
    changed = git_changed_files()
    if not changed:
        emit({})
        return 0

    missing: dict[str, list[str]] = {}
    for path in sorted(changed):
        docs = expected_docs(path)
        if not docs:
            continue
        # OK if at least one related living doc was updated this session.
        if any(doc in changed for doc in docs):
            continue
        missing[path] = docs

    if not missing:
        emit({})
        return 0

    lines = [
        "Docs sync check: code changed but related living docs were not updated in this session.",
        "Update the matching docs before finishing:",
    ]
    for path, docs in missing.items():
        doc_list = ", ".join(f"`{doc}`" for doc in docs)
        lines.append(f"- `{path}` -> {doc_list}")

    emit({"followup_message": "\n".join(lines)})
    return 0


def main() -> int:
    mode = sys.argv[1] if len(sys.argv) > 1 else "post-tool"
    if mode == "stop":
        return handle_stop()
    return handle_post_tool()


if __name__ == "__main__":
    raise SystemExit(main())
