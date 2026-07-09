#!/usr/bin/env python3
"""Crop excess transparent/white padding from podium PNG assets."""
from pathlib import Path

from PIL import Image
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
PATHS = [
    ROOT / "php-admin/public/htd-admin/assets/ket-thuc-tro-choi.png",
    ROOT / "prototype/assets/ket-thuc-tro-choi.png",
]


def content_bbox(im: Image.Image) -> tuple[int, int, int, int]:
    arr = np.array(im.convert("RGBA"))
    alpha = arr[:, :, 3]
    rgb = arr[:, :, :3]
    # Keep non-transparent pixels that are not near-white background.
    mask = (alpha > 10) & ~(
        (rgb[:, :, 0] > 245) & (rgb[:, :, 1] > 245) & (rgb[:, :, 2] > 245)
    )
    ys, xs = np.where(mask)
    if len(xs) == 0:
        raise RuntimeError("no visible content found")
    return int(xs.min()), int(ys.min()), int(xs.max()) + 1, int(ys.max()) + 1


def crop_image(path: Path, padding: int = 2) -> None:
    im = Image.open(path).convert("RGBA")
    x0, y0, x1, y1 = content_bbox(im)
    x0 = max(0, x0 - padding)
    y0 = max(0, y0 - padding)
    x1 = min(im.width, x1 + padding)
    y1 = min(im.height, y1 + padding)
    cropped = im.crop((x0, y0, x1, y1))
    print(f"{path}: {im.size} -> {cropped.size} bbox=({x0},{y0},{x1},{y1})")
    cropped.save(path, optimize=True)


def main() -> None:
    for path in PATHS:
        if not path.exists():
            print(f"skip missing: {path}")
            continue
        crop_image(path)


if __name__ == "__main__":
    main()
