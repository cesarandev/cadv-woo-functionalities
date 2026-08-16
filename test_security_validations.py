"""Regression checks for the July 2026 security assessment.

Run the current-risk checks with:
    python -m unittest -v test_security_validations.py

After applying the remediation plan, run the target-state checks with:
    $env:CADV_SECURITY_POST_FIX='1'
    python -m unittest -v test_security_validations.py

The first group documents that the reviewed revision still contains the
identified patterns. The second group is skipped until remediation work starts
and then becomes the acceptance suite for the fixes.
"""

from __future__ import annotations

import os
import re
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parent
POST_FIX = os.environ.get("CADV_SECURITY_POST_FIX") == "1"


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


def method_body(source: str, method_name: str) -> str:
    """Return a PHP method body using brace balancing."""
    match = re.search(
        rf"(?:public|private|protected)\s+function\s+{re.escape(method_name)}\s*\([^)]*\)\s*\{{",
        source,
    )
    if not match:
        raise AssertionError(f"Method {method_name} was not found")

    start = match.start()
    cursor = match.end()
    depth = 1
    while cursor < len(source) and depth:
        if source[cursor] == "{":
            depth += 1
        elif source[cursor] == "}":
            depth -= 1
        cursor += 1
    return source[start:cursor]


class CurrentRiskDetectionTests(unittest.TestCase):
    """Pre-fix evidence: these pass while the reviewed risks remain present."""

    def test_public_forms_have_machine_solvable_captcha_fallback(self) -> None:
        source = read("includes/class-cadv-woo-functionalities.php")
        renderer = method_body(source, "render_public_captcha_fields")
        validator = method_body(source, "validate_public_submission")
        self.assertIn("$left    = wp_rand( 2, 9 );", renderer)
        self.assertIn("$right   = wp_rand( 1, 9 );", renderer)
        self.assertIn("$captcha['left']", validator)
        self.assertIn("$captcha['right']", validator)

    def test_post_grid_ajax_has_no_rate_limit_and_accepts_rand(self) -> None:
        source = read("includes/class-cadv-post-grid.php")
        handler = method_body(source, "handle_ajax")
        self.assertNotRegex(handler, r"rate[_ -]?limit|consume_")
        self.assertIn("'rand'", source)
        self.assertNotRegex(handler, r"min\s*\(\s*\d+\s*,\s*.*\$page")

    def test_pdf_rasterizer_has_no_resource_budget(self) -> None:
        source = read("includes/class-cadv-woo-functionalities.php")
        renderer = method_body(source, "handle_technical_sheet_preview_page")
        counter = method_body(source, "get_protected_pdf_page_count")
        combined = renderer + counter
        self.assertIn("readImage", combined)
        self.assertNotRegex(
            combined,
            r"setResourceLimit|filesize\s*\([^)]*\)\s*>|MAX_(?:PDF|PREVIEW)_(?:BYTES|PAGES)",
        )

    def test_update_server_accepts_legacy_query_token(self) -> None:
        source = read("update-server/index.php")
        self.assertRegex(source, r"\$_GET\s*\[\s*'token'\s*\]")

    def test_plugin_has_no_wordpress_privacy_exporter_or_eraser(self) -> None:
        source = "\n".join(
            path.read_text(encoding="utf-8")
            for path in ROOT.rglob("*.php")
            if ".git" not in path.parts
        )
        self.assertNotIn("wp_privacy_personal_data_exporters", source)
        self.assertNotIn("wp_privacy_personal_data_erasers", source)


@unittest.skipUnless(
    POST_FIX,
    "Target-state checks are enabled with CADV_SECURITY_POST_FIX=1.",
)
class RemediatedTargetStateTests(unittest.TestCase):
    """Post-fix acceptance checks for the recommended remediation."""

    def test_public_forms_fail_closed_without_strong_bot_protection(self) -> None:
        source = read("includes/class-cadv-woo-functionalities.php")
        renderer = method_body(source, "render_public_captcha_fields")
        validator = method_body(source, "validate_public_submission")
        self.assertNotIn("$left    = wp_rand( 2, 9 );", renderer)
        self.assertNotIn("$captcha['left']", validator)
        self.assertRegex(
            renderer + validator,
            r"recaptcha|turnstile",
            "A production-grade bot control must remain wired to public forms.",
        )

    def test_post_grid_ajax_has_rate_limit_page_cap_and_no_rand(self) -> None:
        source = read("includes/class-cadv-post-grid.php")
        handler = method_body(source, "handle_ajax")
        self.assertRegex(handler, r"rate[_ -]?limit|consume_")
        self.assertRegex(handler, r"min\s*\(\s*\d+\s*,\s*.*\$page")
        self.assertNotIn("'rand'", source)

    def test_pdf_rasterizer_enforces_a_resource_budget(self) -> None:
        source = read("includes/class-cadv-woo-functionalities.php")
        renderer = method_body(source, "handle_technical_sheet_preview_page")
        counter = method_body(source, "get_protected_pdf_page_count")
        self.assertRegex(
            renderer + counter,
            r"setResourceLimit|MAX_(?:PDF|PREVIEW)_(?:BYTES|PAGES)",
        )

    def test_update_server_no_longer_accepts_query_token(self) -> None:
        self.assertNotRegex(
            read("update-server/index.php"),
            r"\$_GET\s*\[\s*'token'\s*\]",
        )

    def test_plugin_registers_privacy_export_and_erasure_hooks(self) -> None:
        source = "\n".join(
            path.read_text(encoding="utf-8")
            for path in ROOT.rglob("*.php")
            if ".git" not in path.parts
        )
        self.assertIn("wp_privacy_personal_data_exporters", source)
        self.assertIn("wp_privacy_personal_data_erasers", source)


if __name__ == "__main__":
    unittest.main()
