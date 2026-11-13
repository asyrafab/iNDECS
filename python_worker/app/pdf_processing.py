from __future__ import annotations

import hashlib
from pathlib import Path
from typing import Any

import fitz
from PIL import Image

from .image_regions import make_regions
from .schemas import WorkerRequest


def _render(page: fitz.Page, path: Path, max_dimension: int, quality: int = 86) -> None:
    rect = page.rect
    scale = min(max_dimension / max(rect.width, rect.height), 3.0)
    pixmap = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False, colorspace=fitz.csRGB)
    pixmap.save(str(path))
    if path.suffix.lower() in {".jpg", ".jpeg"}:
        with Image.open(path) as image:
            image.save(path, "JPEG", quality=quality, optimize=True)


def process_pdf(request: WorkerRequest) -> dict[str, Any]:
    document = fitz.open(request.file_path)
    if document.needs_pass:
        document.close()
        return {"page_count": 0, "pages": [], "warnings": ["PDF_ENCRYPTED"], "is_encrypted": True}

    pages: list[dict[str, Any]] = []
    warnings: list[str] = []
    request.output_dir.mkdir(parents=True, exist_ok=True)
    region_dir = request.output_dir / "regions"

    for index, page in enumerate(document):
        page_number = index + 1
        # Sort by visual position so filled form values remain beside their labels.
        text = page.get_text("text", sort=True).strip() if request.extract_text else ""
        rect = page.rect
        is_large = max(rect.width, rect.height) > 1200
        content_type = "DRAWING_LARGE_FORMAT" if is_large else ("TEXT_RICH" if len(text) >= 100 else "IMAGE_ONLY")
        text_path = request.output_dir / f"page-{page_number}.txt"
        if request.extract_text:
            text_path.write_text(text, encoding="utf-8")

        thumbnail_path = request.output_dir / f"page-{page_number}-thumb.jpg"
        preview_path = request.output_dir / f"page-{page_number}-preview.jpg"
        high_res_path: Path | None = None
        regions: list[dict[str, Any]] = []
        if request.render:
            _render(page, thumbnail_path, 360, 80)
            _render(page, preview_path, 1440)
            if is_large:
                high_res_path = request.output_dir / f"page-{page_number}-high.jpg"
                _render(page, high_res_path, 2600, 90)
                if request.make_regions:
                    regions = make_regions(high_res_path, region_dir, page_number)

        page_hash = hashlib.sha256((text + f"|{rect.width}|{rect.height}").encode("utf-8")).hexdigest()
        pages.append({
            "page": page_number,
            "width": round(rect.width, 2),
            "height": round(rect.height, 2),
            "rotation": page.rotation,
            "content_type": content_type,
            "embedded_text_path": str(text_path) if request.extract_text else None,
            "text_length": len(text),
            "thumbnail_path": str(thumbnail_path) if request.render else None,
            "preview_path": str(preview_path) if request.render else None,
            "high_res_path": str(high_res_path) if high_res_path else None,
            "page_hash": page_hash,
            "regions": regions,
        })

    document.close()
    return {"page_count": len(pages), "pages": pages, "warnings": warnings, "is_encrypted": False}


def process_image(request: WorkerRequest) -> dict[str, Any]:
    request.output_dir.mkdir(parents=True, exist_ok=True)
    with Image.open(request.file_path) as source:
        image = source.convert("RGB")
        width, height = image.size
        thumbnail_path = request.output_dir / "page-1-thumb.jpg"
        preview_path = request.output_dir / "page-1-preview.jpg"
        thumb = image.copy()
        thumb.thumbnail((360, 360))
        thumb.save(thumbnail_path, "JPEG", quality=80)
        preview = image.copy()
        preview.thumbnail((1440, 1440))
        preview.save(preview_path, "JPEG", quality=86)

    return {
        "page_count": 1,
        "pages": [{
            "page": 1,
            "width": width,
            "height": height,
            "rotation": 0,
            "content_type": "IMAGE_ONLY",
            "embedded_text_path": None,
            "text_length": 0,
            "thumbnail_path": str(thumbnail_path),
            "preview_path": str(preview_path),
            "high_res_path": None,
            "page_hash": hashlib.sha256(request.file_path.read_bytes()).hexdigest(),
            "regions": [],
        }],
        "warnings": ["OCR_RECOMMENDED"],
        "is_encrypted": False,
    }
