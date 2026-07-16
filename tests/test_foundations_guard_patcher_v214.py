#!/usr/bin/env python3
from pathlib import Path
import shutil, subprocess, tempfile, sys

root = Path(__file__).resolve().parents[1]
patcher = root / 'apply_foundations_native_html_publication_fix_v214.py'

tmp = Path(tempfile.mkdtemp(prefix='sc-fnd-v214-'))
try:
    includes = tmp / 'sustainable-catalyst-library/includes'
    includes.mkdir(parents=True)
    (includes / 'class-sc-library-foundation-pages.php').write_text("""<?php
final class X {
 public function prevent_publish_without_pdf( $data, $postarr ) {
  if ( 'sc_foundation_doc' !== ( $data['post_type'] ?? '' ) ) { return $data; }
  $post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
  $attachment_id = 0;
  if ( ! $attachment_id ) { $data['post_status'] = 'draft'; self::$validation_error = 'missing_pdf'; }
  return $data;
 }
}
""", encoding='utf-8')
    (includes / 'class-sc-library-pdf-conversion-reliability.php').write_text("""<?php
final class Y {
 public function validate_publication( $data, $postarr ) {
  if ( 'sc_foundation_doc' !== ( $data['post_type'] ?? '' ) ) { return $data; }
  $post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
  return $this->force_draft( $data, 'conversion_incomplete', __( 'The PDF conversion must be completed before publishing.', 'x' ) );
 }
}
""", encoding='utf-8')
    subprocess.run(['python3', str(patcher), str(tmp)], check=True)
    subprocess.run(['python3', str(patcher), str(tmp)], check=True)
    for path in includes.glob('*.php'):
        text = path.read_text(encoding='utf-8')
        assert text.count('Foundations v2.1.4 native HTML publication exemption') == 1
        assert text.index('native HTML publication exemption') < min(
            [i for i in [text.find('missing_pdf'), text.find('conversion_incomplete')] if i >= 0]
        )
    print('PASS: patcher is idempotent and inserts both exemptions before retained guards.')
finally:
    shutil.rmtree(tmp)
