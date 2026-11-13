from __future__ import annotations

from pathlib import Path
from typing import Any

from PIL import Image


REGIONS = {
    "TITLE_BLOCK": (0.62, 0.67, 1.0, 1.0),
    "LEGEND": (0.68, 0.05, 1.0, 0.55),
}


def make_regions(image_path: Path, output_dir: Path, page_number: int) -> list[dict[str, Any]]:
    output_dir.mkdir(parents=True, exist_ok=True)
    regions: list[dict[str, Any]] = []

    with Image.open(image_path) as image:
        width, height = image.size
        for label, box in REGIONS.items():
            left, top, right, bottom = box
            crop = image.crop((int(left * width), int(top * height), int(right * width), int(bottom * height)))
            path = output_dir / f"page-{page_number}-{label.lower()}.jpg"
            crop.save(path, "JPEG", quality=88, optimize=True)
            regions.append({
                "region_type": label,
                "label": label.replace("_", " ").title(),
                "bbox_normalised": [left, top, right, bottom],
                "image_path": str(path),
                "text": None,
                "confidence": 0.5,
            })

    return regions
