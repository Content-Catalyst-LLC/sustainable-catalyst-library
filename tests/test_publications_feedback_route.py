"""SC-PUB-TOPIC-01 contract: Publications routes requests to existing Feedback."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
TEMPLATE = ROOT / "sustainable-catalyst-library/templates/publications-interest-discovery.php"
CSS = ROOT / "sustainable-catalyst-library/assets/css/sc-library-field-spotlights.css"
SPOTLIGHT = ROOT / "sustainable-catalyst-library/includes/class-sc-library-field-spotlights.php"
DUPLICATE_INTAKE = ROOT / "sustainable-catalyst-library/includes/class-sc-library-publications-topic-request.php"


class FeedbackRouteTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.template = TEMPLATE.read_text(encoding="utf-8")
        cls.css = CSS.read_text(encoding="utf-8")
        cls.spotlights = SPOTLIGHT.read_text(encoding="utf-8")

    def test_requests_link_to_verified_first_party_feedback_route(self):
        self.assertIn("home_url( '/feature-suggestions/' )", self.template)
        self.assertIn("Request a Topic", self.template)
        self.assertIn("Can't find the subject", self.template)
        self.assertIn('class="sc-publications-interest__feedback"', self.template)
        self.assertIn("esc_url(", self.template)

    def test_no_duplicate_form_post_type_or_unauthorized_context_forwarding(self):
        self.assertFalse(DUPLICATE_INTAKE.exists())
        self.assertNotIn("sc_pub_topic_nonce", self.template)
        self.assertNotIn("sc_pub_topic_title", self.template)
        self.assertNotIn("admin-post.php", self.template)
        self.assertNotIn("SC_LIBRARY_PUBLICATIONS_TOPIC_REQUEST", self.spotlights)
        self.assertNotIn("sc_pub_topic_request", self.template)
        self.assertNotIn("feature-suggestions/?", self.template)

    def test_link_has_local_focus_style_and_no_extra_intake_controls(self):
        self.assertIn(".sc-publications-interest__feedback a:focus-visible", self.css)
        self.assertNotIn(".sc-publications-interest__topic-form", self.css)


if __name__ == "__main__":
    unittest.main()
