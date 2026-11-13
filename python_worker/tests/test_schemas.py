from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase

from app.schemas import WorkerRequest


class WorkerRequestTest(TestCase):
    def test_rejects_missing_input_file(self) -> None:
        with TemporaryDirectory() as temporary:
            root = Path(temporary)
            with self.assertRaises(ValueError):
                WorkerRequest.from_payload({"file_path": str(root / "missing.pdf"), "output_dir": str(root / "out")})
