#!/usr/bin/env python3
"""Trim black padding from duck sprite GIFs (all frames share one crop box)."""
from __future__ import annotations

import sys
from pathlib import Path

try:
    from PIL import Image, ImageSequence
except ImportError:
    print("Pillow required: pip install Pillow", file=sys.stderr)
    sys.exit(1)


def is_padding_pixel(r: int, g: int, b: int, a: int, threshold: int = 18) -> bool:
    if a < 40:
        return True
    if r <= threshold and g <= threshold and b <= threshold:
        return True
    # Nền xám/trắng dư trong asset sprite
    return r >= 210 and g >= 210 and b >= 210


def content_bbox(frame: Image.Image, threshold: int = 18) -> tuple[int, int, int, int] | None:
    rgba = frame.convert("RGBA")
    pixels = rgba.load()
    w, h = rgba.size
    min_x, min_y, max_x, max_y = w, h, -1, -1
    for y in range(h):
        for x in range(w):
            r, g, b, a = pixels[x, y]
            if not is_padding_pixel(r, g, b, a, threshold):
                min_x = min(min_x, x)
                min_y = min(min_y, y)
                max_x = max(max_x, x)
                max_y = max(max_y, y)
    if max_x < 0:
        return None
    return min_x, min_y, max_x + 1, max_y + 1


def union_bbox(boxes: list[tuple[int, int, int, int]]) -> tuple[int, int, int, int]:
    return (
        min(b[0] for b in boxes),
        min(b[1] for b in boxes),
        max(b[2] for b in boxes),
        max(b[3] for b in boxes),
    )


def trim_gif(path: Path) -> bool:
    im = Image.open(path)
    frame_boxes: list[tuple[int, int, int, int]] = []
    frames: list[Image.Image] = []
    durations: list[int] = []

    for frame in ImageSequence.Iterator(im):
        bb = content_bbox(frame)
        if bb:
            frame_boxes.append(bb)
        frames.append(frame.convert("RGBA"))
        durations.append(frame.info.get("duration", im.info.get("duration", 80)))

    if not frame_boxes:
        print(f"skip (empty): {path}")
        return False

    crop = union_bbox(frame_boxes)
    cropped = [f.crop(crop) for f in frames]
    old_size = im.size
    new_size = cropped[0].size

    cropped[0].save(
        path,
        save_all=True,
        append_images=cropped[1:],
        loop=im.info.get("loop", 0),
        duration=durations,
        disposal=2,
        optimize=False,
    )
    print(f"trimmed {path.name}: {old_size} -> {new_size}")
    return True


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    targets = [
        root / "php-admin/public/htd-admin/assets/duck-race/ducks",
        root / "prototype/assets/duck-race",
        root / "prototype/assets/duck-race/ducks",
    ]
    seen: set[Path] = set()
    for folder in targets:
        if not folder.is_dir():
            continue
        for gif in sorted(folder.glob("*.gif")):
            if gif in seen:
                continue
            seen.add(gif)
            trim_gif(gif)


if __name__ == "__main__":
    main()
