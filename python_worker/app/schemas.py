from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any


@dataclass(frozen=True)
class WorkerRequest:
    file_path: Path
    output_dir: Path
    render: bool = True
    extract_text: bool = True
    make_regions: bool = True

    @classmethod
    def from_payload(cls, payload: dict[str, Any]) -> "WorkerRequest":
        if not isinstance(payload, dict):
            raise ValueError("Worker input must be a JSON object.")

        raw_file_path = str(payload.get("file_path", "")).strip()
        raw_output_dir = str(payload.get("output_dir", "")).strip()
        if not raw_output_dir:
            raise ValueError("Output directory is required.")

        file_path = Path(raw_file_path).resolve()
        output_dir = Path(raw_output_dir).resolve()
        if not file_path.is_file():
            raise ValueError("Input document does not exist.")

        return cls(
            file_path=file_path,
            output_dir=output_dir,
            render=bool(payload.get("render", True)),
            extract_text=bool(payload.get("extract_text", True)),
            make_regions=bool(payload.get("make_regions", True)),
        )
