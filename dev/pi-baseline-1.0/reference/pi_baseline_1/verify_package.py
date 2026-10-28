"""Static safety checks for the Pi baseline distribution."""
from pathlib import Path
import unittest
R = Path(__file__).resolve().parent

class BaselinePolicy(unittest.TestCase):
    def test_compose_has_no_host_ports(self):
        text = (R / 'compose.library.yaml').read_text()
        self.assertNotIn('ports:', text)
        self.assertIn('internal: true', text)
        self.assertIn('external: true', text)
        self.assertIn('SC_LIBRARY_EMBEDDING_PROVIDER: disabled', text)
        self.assertIn('SC_LIBRARY_JOB_DISPATCH_ENABLED: "false"', text)
        self.assertIn('SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY: ""', text)
        self.assertIn('POSTGRES_PASSWORD', (R / 'baseline.sh').read_text())
    def test_backend_only_private_net(self):
        text = (R / 'compose.library.yaml').read_text()
        backend = text.split('  library-backend:\n',1)[1].split('  adapter:\n',1)[0]
        self.assertIn('networks: [library-private]', backend)
        self.assertNotIn('sc-internal', backend)
    def test_adapter_only_read_routes(self):
        text = (R / 'adapter.py').read_text()
        self.assertIn('def do_GET', text)
        self.assertIn('def do_POST', text)
        self.assertIn('self.send_error(405)', text)
        self.assertIn('"/search": "/api/library/v1/research-interface/search"', text)
        self.assertNotIn('urlopen(self.path', text)
    def test_wordpress_search_protected(self):
        text = (R / 'sc-pi-library-bridge.php').read_text()
        self.assertIn("current_user_can('manage_options')", text)
        self.assertIn("wp_create_nonce('wp_rest')", text)
        self.assertNotIn('update_option(', text)
        self.assertNotIn('wp_insert_post(', text)
    def test_workbench_recreation_only(self):
        text = (R / 'baseline.sh').read_text()
        self.assertIn('--force-recreate workbench', text)
        self.assertIn('test "$before" != "$after"', text)
        self.assertIn('workbench-data-before.tar', text)
        self.assertNotIn('docker system prune', text)
        self.assertNotIn('down -v', text)

if __name__ == '__main__':
    unittest.main(verbosity=2)
