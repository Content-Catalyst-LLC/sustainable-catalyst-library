<?php
/** SC-PUB-RELEASE-01: isolated canonical-page entryway acceptance fixture. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('SC_LIBRARY_DIR', dirname(__DIR__) . '/sustainable-catalyst-library/');
$GLOBALS['sc_fixture_unique_id'] = 0;
function wp_unique_id(string $prefix): string {return $prefix . ++$GLOBALS['sc_fixture_unique_id'];}
function esc_attr($s): string {return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function esc_html($s): string {return esc_attr($s);}
function esc_attr_e(string $s, string $domain=''): void {echo esc_attr($s);}
function esc_html_e(string $s, string $domain=''): void {echo esc_html($s);}
function sanitize_title(string $s): string {return preg_replace('/[^a-z0-9-]+/', '-', strtolower($s));}
function home_url(string $path): string {return 'https://sustainablecatalyst.com' . $path;}
function esc_url(string $s): string {return esc_attr($s);}
final class SC_Pub_Entry_Test_Renderer {
    public function __construct(private bool $canonical) {}
    private function is_canonical_publications_page(): bool {return $this->canonical;}
    public function render(): string {
        $fields = array();
        $publications_directory = array();
        ob_start();
        include SC_LIBRARY_DIR . 'templates/field-spotlights.php';
        return (string) ob_get_clean();
    }
}
function check(bool $value, string $description): void {
    if (!$value) {
        fwrite(STDERR, "FAIL: $description\n");
        exit(1);
    }
    echo "PASS: $description\n";
}
$canonical = (new SC_Pub_Entry_Test_Renderer(true))->render();
$other = (new SC_Pub_Entry_Test_Renderer(false))->render();
check(str_contains($canonical,'sc-field-stack__discovery-paths'), 'canonical page has entryway');
check(substr_count($canonical,'class="sc-field-stack__discovery-paths"')===1, 'entryway appears exactly once');
check(str_contains($canonical,'href="https://sustainablecatalyst.com/research-hub/"'), 'real Research Hub destination');
check(str_contains($canonical,'href="https://sustainablecatalyst.com/feature-suggestions/"'), 'existing Feedback destination');
check(str_contains($canonical,'Request a topic'), 'descriptive topic request');
check(str_contains($canonical,'Publications by field'), 'original index remains');
check(str_contains($canonical,'data-sc-field-stack="v4.3.22.4"'), 'existing widget semantics remain');
check(!str_contains($canonical,'<form'), 'no duplicate feedback intake');
check(!str_contains($other,'sc-field-stack__discovery-paths'), 'other embeds unaffected');
echo "SC-PUB-RELEASE-01 acceptance passed.\n";
