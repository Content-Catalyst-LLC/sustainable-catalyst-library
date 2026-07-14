# OCR Provider Integration — v2.4.0

## Provider registration

```php
add_filter( 'sc_library_ocr_providers', function( array $providers ): array {
    $providers['custom'] = [
        'name' => 'Custom OCR',
        'available' => true,
        'description' => 'Site-specific OCR adapter.',
        'languages' => [ 'eng', 'spa' ],
    ];
    return $providers;
} );
```

## Page processing

```php
add_filter(
    'sc_library_ocr_process_page',
    function( $result, array $context, string $provider_id ) {
        if ( 'custom' !== $provider_id ) {
            return $result;
        }

        return [
            'text' => 'Recognized page text',
            'confidence' => 92.5,
            'language' => 'eng',
            'provider' => 'custom',
            'warnings' => [],
        ];
    },
    10,
    3
);
```

The context contains version, document ID, attachment ID, page, language, local PDF path, public PDF URL, and checksum. Providers must return non-empty text and confidence from 0 to 100.

## External endpoint response

```json
{
  "text": "Recognized page text",
  "confidence": 91.4,
  "language": "eng",
  "provider": "example-ocr",
  "warnings": []
}
```

The request includes `X-SC-OCR-Signature`, an HMAC-SHA256 signature of the JSON body.
