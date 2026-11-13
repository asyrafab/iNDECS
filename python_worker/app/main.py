from __future__ import annotations

import json
import sys

from .pdf_processing import process_image, process_pdf
from .schemas import WorkerRequest


def main() -> int:
    try:
        payload = json.load(sys.stdin)
        request = WorkerRequest.from_payload(payload)
        result = process_pdf(request) if request.file_path.suffix.lower() == ".pdf" else process_image(request)
        json.dump(result, sys.stdout, ensure_ascii=False)
        return 0
    except Exception as error:  # The PHP boundary records a safe error; details stay in worker logs.
        print(f"Document worker failed: {type(error).__name__}: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
