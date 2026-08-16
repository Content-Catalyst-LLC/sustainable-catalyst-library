<?php
/**
 * Research Identity, Authority & Persistent Identifier Network — v5.2.0.
 *
 * Composes persistent identifiers already present on canonical public Library
 * records and explicitly published federation metadata. It normalizes and
 * validates identifier syntax/checksums locally, preserves provenance, and
 * surfaces ambiguity without automatically merging, rewriting, or asserting
 * authorship, affiliation, truth, ownership, or access entitlement.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Research_Identity_Authority_Network {
    public const VERSION = '5.2.0';
    public const SCHEMA = 'sc-library-research-identity-authority-network/1.0';
    public const IDENTIFIER_SCHEMA = 'sc-library-persistent-identifier/1.0';
    public const RECORD_SCHEMA = 'sc-library-research-identity-record/1.0';
    public const RESOLUTION_SCHEMA = 'sc-library-identifier-resolution/1.0';
    public const NETWORK_SCHEMA = 'sc-library-identifier-network/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-identity';
    public const MAX_LOCAL_RECORDS = 260;
    public const MAX_FEDERATION_MANIFESTS = 120;
    public const MAX_FEDERATION_RECORDS = 240;
    public const MAX_MATCHES = 60;
    public const MAX_IDENTIFIERS_PER_RECORD = 24;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_research_identity_authority', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_api_public_object_payload', array( $this, 'filter_public_object_payload' ), 20, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_headers' ), 30, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'canonical_public_records_reused' => true,
            'citation_source_identifier_fields_reused' => true,
            'metadata_quality_resolution_reused' => true,
            'named_entity_authority_reused' => true,
            'published_federation_metadata_reused' => true,
            'creates_parallel_identity_store' => false,
            'creates_parallel_entity_store' => false,
            'raw_post_meta_exposed' => false,
            'identifier_validation_mode' => 'local-syntax-and-checksum',
            'external_registry_verification_performed' => false,
            'automatic_entity_merge' => false,
            'automatic_record_merge' => false,
            'automatic_identifier_assignment' => false,
            'automatic_authorship_assertion' => false,
            'automatic_affiliation_assertion' => false,
            'automatic_truth_scoring' => false,
            'access_entitlement_inferred' => false,
            'identifier_match_is_not_identity_proof' => true,
            'ambiguity_preserved' => true,
            'provenance_preserved' => true,
            'private_research_included' => false,
            'public_get_only' => true,
            'remote_network_calls_during_resolution' => false,
            'automatic_publication' => false,
            'automatic_federation_acceptance' => false,
            'automatic_workspace_write' => false,
        );
    }

    public static function scheme_registry() {
        return array(
            'doi' => array( 'label' => 'DOI', 'base_url' => 'https://doi.org/' ),
            'orcid' => array( 'label' => 'ORCID', 'base_url' => 'https://orcid.org/' ),
            'ror' => array( 'label' => 'ROR', 'base_url' => 'https://ror.org/' ),
            'isbn' => array( 'label' => 'ISBN', 'base_url' => 'https://isbnsearch.org/isbn/' ),
            'issn' => array( 'label' => 'ISSN', 'base_url' => 'https://portal.issn.org/resource/ISSN/' ),
            'wikidata' => array( 'label' => 'Wikidata', 'base_url' => 'https://www.wikidata.org/wiki/' ),
            'pmid' => array( 'label' => 'PMID', 'base_url' => 'https://pubmed.ncbi.nlm.nih.gov/' ),
        );
    }

    private static function clean( $value, $limit = 240 ) {
        $value = trim( (string) $value );
        if ( function_exists( 'sanitize_text_field' ) ) { $value = sanitize_text_field( $value ); }
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    public static function normalize_identifier( $scheme, $value ) {
        $scheme = strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $scheme ) );
        $value = trim( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
        if ( '' === $value ) { return ''; }
        switch ( $scheme ) {
            case 'doi':
                $value = strtolower( $value );
                $value = preg_replace( '#^https?://(?:dx\.)?doi\.org/#i', '', $value );
                $value = preg_replace( '/^doi:\s*/i', '', $value );
                return trim( $value );
            case 'orcid':
                $value = preg_replace( '#^https?://orcid\.org/#i', '', $value );
                $raw = strtoupper( preg_replace( '/[^0-9X]/i', '', $value ) );
                if ( 16 !== strlen( $raw ) ) { return ''; }
                return substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 4 ) . '-' . substr( $raw, 8, 4 ) . '-' . substr( $raw, 12, 4 );
            case 'ror':
                $value = strtolower( preg_replace( '#^https?://ror\.org/#i', '', $value ) );
                return trim( $value, "/ \t\n\r\0\x0B" );
            case 'isbn':
                return strtoupper( preg_replace( '/[^0-9Xx]/', '', $value ) );
            case 'issn':
                $raw = strtoupper( preg_replace( '/[^0-9Xx]/', '', $value ) );
                return 8 === strlen( $raw ) ? substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 4 ) : '';
            case 'wikidata':
                $value = preg_replace( '#^https?://(?:www\.)?wikidata\.org/(?:wiki|entity)/#i', '', $value );
                return strtoupper( trim( $value, "/ \t\n\r\0\x0B" ) );
            case 'pmid':
                $value = preg_replace( '#^https?://pubmed\.ncbi\.nlm\.nih\.gov/#i', '', $value );
                return preg_replace( '/[^0-9]/', '', $value );
        }
        return self::clean( $value, 180 );
    }

    public static function valid_identifier( $scheme, $value ) {
        $scheme = strtolower( (string) $scheme );
        $value = self::normalize_identifier( $scheme, $value );
        if ( '' === $value ) { return false; }
        if ( 'doi' === $scheme ) {
            if ( class_exists( 'SC_Library_Citation_Source_Reliability' ) ) { return SC_Library_Citation_Source_Reliability::valid_doi( $value ); }
            return 1 === preg_match( '/^10\.\d{4,9}\/\S+$/i', $value );
        }
        if ( 'orcid' === $scheme ) { return self::valid_orcid( $value ); }
        if ( 'ror' === $scheme ) { return 1 === preg_match( '/^0[0-9a-hjkmnp-tv-z]{6}[0-9]{2}$/', $value ); }
        if ( 'isbn' === $scheme ) { return self::valid_isbn( $value ); }
        if ( 'issn' === $scheme ) { return self::valid_issn( $value ); }
        if ( 'wikidata' === $scheme ) { return 1 === preg_match( '/^Q[1-9][0-9]*$/', $value ); }
        if ( 'pmid' === $scheme ) { return 1 === preg_match( '/^[0-9]{1,9}$/', $value ); }
        return false;
    }

    private static function valid_orcid( $value ) {
        $digits = preg_replace( '/[^0-9X]/', '', strtoupper( (string) $value ) );
        if ( 16 !== strlen( $digits ) ) { return false; }
        $total = 0;
        for ( $i = 0; $i < 15; $i++ ) {
            if ( ! ctype_digit( $digits[ $i ] ) ) { return false; }
            $total = ( $total + (int) $digits[ $i ] ) * 2;
        }
        $result = ( 12 - ( $total % 11 ) ) % 11;
        $check = 10 === $result ? 'X' : (string) $result;
        return $check === $digits[15];
    }

    private static function valid_isbn( $value ) {
        $raw = self::normalize_identifier( 'isbn', $value );
        if ( 10 === strlen( $raw ) ) {
            $sum = 0;
            for ( $i = 0; $i < 10; $i++ ) {
                $digit = ( 9 === $i && 'X' === $raw[$i] ) ? 10 : ( ctype_digit( $raw[$i] ) ? (int) $raw[$i] : -1 );
                if ( $digit < 0 ) { return false; }
                $sum += ( 10 - $i ) * $digit;
            }
            return 0 === $sum % 11;
        }
        if ( 13 === strlen( $raw ) && ctype_digit( $raw ) ) {
            $sum = 0;
            for ( $i = 0; $i < 12; $i++ ) { $sum += (int) $raw[$i] * ( 0 === $i % 2 ? 1 : 3 ); }
            $check = ( 10 - ( $sum % 10 ) ) % 10;
            return $check === (int) $raw[12];
        }
        return false;
    }

    private static function valid_issn( $value ) {
        $raw = preg_replace( '/[^0-9X]/', '', strtoupper( (string) $value ) );
        if ( 8 !== strlen( $raw ) ) { return false; }
        $sum = 0;
        for ( $i = 0; $i < 8; $i++ ) {
            $digit = ( 7 === $i && 'X' === $raw[$i] ) ? 10 : ( ctype_digit( $raw[$i] ) ? (int) $raw[$i] : -1 );
            if ( $digit < 0 ) { return false; }
            $sum += $digit * ( 8 - $i );
        }
        return 0 === $sum % 11;
    }

    public static function identifier_url( $scheme, $value ) {
        $scheme = strtolower( (string) $scheme );
        $value = self::normalize_identifier( $scheme, $value );
        $registry = self::scheme_registry();
        if ( ! isset( $registry[$scheme] ) || ! self::valid_identifier( $scheme, $value ) ) { return ''; }
        return $registry[$scheme]['base_url'] . rawurlencode( $value );
    }

    private static function make_identifier( $scheme, $value, $role, $provenance, $label = '' ) {
        $normalized = self::normalize_identifier( $scheme, $value );
        if ( '' === $normalized ) { return null; }
        return array(
            'schema' => self::IDENTIFIER_SCHEMA,
            'scheme' => sanitize_key( $scheme ),
            'label' => $label ?: strtoupper( (string) $scheme ),
            'value' => self::clean( $value, 220 ),
            'normalized' => $normalized,
            'syntax_valid' => self::valid_identifier( $scheme, $normalized ),
            'canonical_url' => self::identifier_url( $scheme, $normalized ),
            'role' => sanitize_key( $role ?: 'record' ),
            'provenance' => self::clean( $provenance, 220 ),
            'externally_verified' => false,
        );
    }

    private static function add_identifier( &$out, $scheme, $value, $role, $provenance, $label = '' ) {
        $item = self::make_identifier( $scheme, $value, $role, $provenance, $label );
        if ( ! is_array( $item ) ) { return; }
        $key = $item['scheme'] . ':' . $item['normalized'] . ':' . $item['role'];
        foreach ( $out as $existing ) {
            $existing_key = ( $existing['scheme'] ?? '' ) . ':' . ( $existing['normalized'] ?? '' ) . ':' . ( $existing['role'] ?? '' );
            if ( $key === $existing_key ) { return; }
        }
        if ( count( $out ) < self::MAX_IDENTIFIERS_PER_RECORD ) { $out[] = $item; }
    }

    private static function detect_uri_identifier( $uri, &$out, $role, $provenance ) {
        $uri = trim( (string) $uri );
        if ( '' === $uri ) { return; }
        foreach ( array( 'orcid', 'ror', 'wikidata', 'doi' ) as $scheme ) {
            $normalized = self::normalize_identifier( $scheme, $uri );
            if ( self::valid_identifier( $scheme, $normalized ) ) {
                self::add_identifier( $out, $scheme, $uri, $role, $provenance );
                return;
            }
        }
    }

    public static function identifiers_for_public_record( $type, $post ) {
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) { return array(); }
        $type = sanitize_key( $type );
        $out = array();

        if ( 'research-source' === $type && class_exists( 'SC_Library_Citation_Source_Manager' ) ) {
            $source_id = absint( $post->ID );
            $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, false );
            if ( is_array( $data ) ) {
                self::add_identifier( $out, 'doi', $data['doi'] ?? '', 'record', 'citation-source:doi' );
                self::add_identifier( $out, 'isbn', $data['isbn'] ?? '', 'record', 'citation-source:isbn' );
                self::add_identifier( $out, 'pmid', $data['pmid'] ?? '', 'record', 'citation-source:pmid' );
                $standard = (string) ( $data['standard_number'] ?? '' );
                if ( self::valid_identifier( 'issn', $standard ) ) { self::add_identifier( $out, 'issn', $standard, 'record', 'citation-source:standard-number' ); }
                foreach ( array_slice( (array) ( $data['authors'] ?? array() ), 0, 20 ) as $author ) {
                    if ( ! is_array( $author ) || empty( $author['orcid'] ) ) { continue; }
                    $name = trim( (string) ( $author['given'] ?? '' ) . ' ' . (string) ( $author['family'] ?? '' ) );
                    self::add_identifier( $out, 'orcid', $author['orcid'], 'creator', 'citation-source:author-orcid', $name ?: 'ORCID' );
                }
            }
        } elseif ( 'foundation-document' === $type ) {
            self::add_identifier( $out, 'doi', get_post_meta( $post->ID, '_sc_foundation_doi', true ), 'record', 'foundation-document:doi' );
        } elseif ( 'named-entity' === $type && class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            self::detect_uri_identifier( get_post_meta( $post->ID, SC_Library_Topics_Concepts_Relationships::META_ENTITY_URI, true ), $out, 'authority', 'named-entity:canonical-uri' );
        } elseif ( 'concept' === $type && class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            self::detect_uri_identifier( get_post_meta( $post->ID, SC_Library_Topics_Concepts_Relationships::META_CONCEPT_URI, true ), $out, 'authority', 'concept:external-uri' );
        }

        $explicit_meta = array(
            'doi' => array( '_sc_doi', '_sc_publication_doi', '_sc_library_doi' ),
            'orcid' => array( '_sc_orcid', '_sc_author_orcid' ),
            'ror' => array( '_sc_ror', '_sc_institution_ror' ),
            'isbn' => array( '_sc_isbn', '_sc_publication_isbn' ),
            'issn' => array( '_sc_issn', '_sc_publication_issn' ),
            'wikidata' => array( '_sc_wikidata', '_sc_wikidata_id' ),
            'pmid' => array( '_sc_pmid' ),
        );
        foreach ( $explicit_meta as $scheme => $keys ) {
            foreach ( $keys as $key ) {
                $value = get_post_meta( $post->ID, $key, true );
                if ( '' !== trim( (string) $value ) ) { self::add_identifier( $out, $scheme, $value, 'record', 'approved-meta:' . $key ); }
            }
        }
        return $out;
    }

    public static function record_identity( $type, $id ) {
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return new WP_Error( 'sc_identity_api_missing', __( 'The public Library API is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) ); }
        $profiles = SC_Library_API_Embeds_Interoperability::object_profiles();
        $type = sanitize_key( $type ); $id = absint( $id );
        if ( ! isset( $profiles[$type] ) ) { return new WP_Error( 'sc_identity_type', __( 'Unsupported public object type.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $post = get_post( $id );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || $post->post_type !== $profiles[$type]['post_type'] ) { return new WP_Error( 'sc_identity_not_public', __( 'The requested public record was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $object = SC_Library_API_Embeds_Interoperability::normalize_public_object( $type, $post );
        if ( is_wp_error( $object ) ) { return $object; }
        $identifiers = self::identifiers_for_public_record( $type, $post );
        return array(
            'schema' => self::RECORD_SCHEMA,
            'version' => self::VERSION,
            'object' => $object,
            'identifiers' => $identifiers,
            'identifier_count' => count( $identifiers ),
            'authority_resolution' => array(
                'mode' => 'explicit-identifiers-only',
                'external_registry_verification_performed' => false,
                'identifier_match_is_not_identity_proof' => true,
                'automatic_merge' => false,
            ),
        );
    }

    private static function public_local_matches( $scheme, $normalized ) {
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return array(); }
        $profiles = SC_Library_API_Embeds_Interoperability::object_profiles();
        $post_types = array_values( array_unique( array_column( $profiles, 'post_type' ) ) );
        $posts = get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => self::MAX_LOCAL_RECORDS, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) );
        $types_by_post = array(); foreach ( $profiles as $type => $profile ) { $types_by_post[$profile['post_type']][] = $type; }
        $out = array();
        foreach ( (array) $posts as $post ) {
            if ( ! $post instanceof WP_Post ) { continue; }
            foreach ( (array) ( $types_by_post[$post->post_type] ?? array() ) as $type ) {
                foreach ( self::identifiers_for_public_record( $type, $post ) as $identifier ) {
                    if ( $scheme !== ( $identifier['scheme'] ?? '' ) || $normalized !== ( $identifier['normalized'] ?? '' ) ) { continue; }
                    $object = SC_Library_API_Embeds_Interoperability::normalize_public_object( $type, $post );
                    if ( is_wp_error( $object ) ) { continue; }
                    $out[] = array( 'origin' => 'local', 'match_basis' => 'explicit-identifier', 'record' => $object, 'identifier' => $identifier, 'provenance' => array( 'source' => $identifier['provenance'] ?? '', 'publisher' => get_bloginfo( 'name' ) ) );
                    break 2;
                }
            }
            if ( count( $out ) >= self::MAX_MATCHES ) { break; }
        }
        return $out;
    }

    private static function federation_identifier_candidates( $record ) {
        $out = array(); if ( ! is_array( $record ) ) { return $out; }
        $fields = array( 'identifiers', 'persistent_identifiers' );
        foreach ( $fields as $field ) {
            foreach ( (array) ( $record[$field] ?? array() ) as $entry ) {
                if ( is_array( $entry ) ) {
                    $scheme = sanitize_key( (string) ( $entry['scheme'] ?? $entry['type'] ?? '' ) );
                    $value = (string) ( $entry['normalized'] ?? $entry['value'] ?? $entry['id'] ?? '' );
                    if ( $scheme && $value ) { self::add_identifier( $out, $scheme, $value, (string) ( $entry['role'] ?? 'record' ), 'federation-manifest:identifier' ); }
                }
            }
        }
        foreach ( array( 'doi','orcid','ror','isbn','issn','wikidata','pmid' ) as $scheme ) {
            if ( isset( $record[$scheme] ) ) { self::add_identifier( $out, $scheme, $record[$scheme], 'record', 'federation-manifest:' . $scheme ); }
        }
        foreach ( array( 'canonical_uri', 'external_uri', 'url' ) as $field ) { if ( ! empty( $record[$field] ) ) { self::detect_uri_identifier( $record[$field], $out, 'authority', 'federation-manifest:' . $field ); } }
        return $out;
    }

    private static function federated_matches( $scheme, $normalized ) {
        if ( ! class_exists( 'SC_Library_Global_Research_Federation' ) ) { return array(); }
        $out = array(); $record_count = 0;
        foreach ( array_slice( SC_Library_Global_Research_Federation::published_manifest_ids(), 0, self::MAX_FEDERATION_MANIFESTS ) as $manifest_id ) {
            $state = SC_Library_Global_Research_Federation::manifest_state( absint( $manifest_id ) );
            if ( is_wp_error( $state ) || 'published' !== (string) ( $state['status'] ?? '' ) ) { continue; }
            $manifest = (array) ( $state['manifest'] ?? array() );
            foreach ( (array) ( $manifest['records'] ?? array() ) as $record ) {
                if ( ++$record_count > self::MAX_FEDERATION_RECORDS ) { break 2; }
                foreach ( self::federation_identifier_candidates( $record ) as $identifier ) {
                    if ( $scheme !== ( $identifier['scheme'] ?? '' ) || $normalized !== ( $identifier['normalized'] ?? '' ) ) { continue; }
                    $out[] = array(
                        'origin' => 'federated',
                        'match_basis' => 'explicit-identifier',
                        'record' => array(
                            'type' => sanitize_key( (string) ( $record['type'] ?? $record['kind'] ?? 'external' ) ),
                            'title' => self::clean( $record['title'] ?? 'Federated record', 240 ),
                            'canonical_id' => self::clean( $record['canonical_id'] ?? $record['id'] ?? $record['reference_id'] ?? '', 240 ),
                            'canonical_url' => esc_url_raw( (string) ( $record['url'] ?? $record['canonical_url'] ?? '' ) ),
                        ),
                        'identifier' => $identifier,
                        'provenance' => array(
                            'node_id' => self::clean( $manifest['origin_node_id'] ?? '', 180 ),
                            'manifest_id' => absint( $manifest_id ),
                            'manifest_urn' => self::clean( $manifest['manifest_urn'] ?? '', 220 ),
                            'manifest_sha256' => self::clean( $manifest['sha256'] ?? $manifest['manifest_sha256'] ?? '', 128 ),
                            'record_provenance' => self::clean( $record['provenance'] ?? '', 220 ),
                        ),
                    );
                    break;
                }
                if ( count( $out ) >= self::MAX_MATCHES ) { return $out; }
            }
        }
        return $out;
    }

    public static function resolve_identifier( $scheme, $value ) {
        $scheme = sanitize_key( $scheme );
        if ( ! isset( self::scheme_registry()[$scheme] ) ) { return new WP_Error( 'sc_identity_scheme', __( 'Unsupported identifier scheme.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $normalized = self::normalize_identifier( $scheme, $value );
        $valid = self::valid_identifier( $scheme, $normalized );
        $matches = $valid ? array_merge( self::public_local_matches( $scheme, $normalized ), self::federated_matches( $scheme, $normalized ) ) : array();
        $matches = array_slice( $matches, 0, self::MAX_MATCHES );
        return array(
            'schema' => self::RESOLUTION_SCHEMA,
            'version' => self::VERSION,
            'query' => array( 'scheme' => $scheme, 'value' => self::clean( $value, 220 ), 'normalized' => $normalized, 'syntax_valid' => $valid, 'canonical_url' => self::identifier_url( $scheme, $normalized ) ),
            'match_count' => count( $matches ),
            'ambiguous' => count( $matches ) > 1,
            'matches' => $matches,
            'resolution' => array(
                'mode' => 'explicit-identifier-match',
                'external_registry_verification_performed' => false,
                'identifier_match_is_not_identity_proof' => true,
                'automatic_merge' => false,
                'automatic_canonical_assignment' => false,
            ),
        );
    }

    public static function network_for_identifier( $scheme, $value ) {
        $resolution = self::resolve_identifier( $scheme, $value );
        if ( is_wp_error( $resolution ) ) { return $resolution; }
        $nodes = array(); $edges = array();
        $identifier_node = 'identifier:' . $resolution['query']['scheme'] . ':' . $resolution['query']['normalized'];
        $nodes[] = array( 'id' => $identifier_node, 'kind' => 'persistent-identifier', 'label' => strtoupper( $resolution['query']['scheme'] ) . ' ' . $resolution['query']['normalized'] );
        foreach ( (array) $resolution['matches'] as $i => $match ) {
            $record = (array) ( $match['record'] ?? array() );
            $node_id = ( $match['origin'] ?? 'local' ) . ':' . ( $record['canonical_id'] ?? (string) $i );
            $nodes[] = array( 'id' => $node_id, 'kind' => 'public-record', 'origin' => $match['origin'] ?? '', 'type' => $record['type'] ?? '', 'label' => $record['title'] ?? '' );
            $edges[] = array( 'from' => $node_id, 'to' => $identifier_node, 'relation' => 'declares-identifier', 'provenance' => $match['provenance'] ?? array() );
        }
        return array( 'schema' => self::NETWORK_SCHEMA, 'version' => self::VERSION, 'query' => $resolution['query'], 'nodes' => $nodes, 'edges' => $edges, 'ambiguous' => $resolution['ambiguous'], 'one_hop_only' => true, 'automatic_merge' => false );
    }

    public function filter_public_object_payload( $payload, $type, $post ) {
        if ( ! is_array( $payload ) || ! $post instanceof WP_Post || 'publish' !== $post->post_status ) { return $payload; }
        $identifiers = self::identifiers_for_public_record( $type, $post );
        $payload['persistent_identifiers'] = $identifiers;
        $payload['identity_url'] = esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/record/' . sanitize_key( $type ) . '/' . absint( $post->ID ) ) );
        $payload['identifier_authority'] = array( 'automatic_merge' => false, 'external_registry_verification_performed' => false );
        return $payload;
    }

    public function register_assets() {
        wp_register_style( 'sc-library-research-identity-v520', SC_LIBRARY_URL . 'assets/css/sc-library-research-identity-v520.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-research-identity-v520', SC_LIBRARY_URL . 'assets/js/sc-library-research-identity-v520.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_capabilities' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/schemes', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_schemes' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/resolve', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_resolve' ), 'args' => array( 'scheme' => array( 'sanitize_callback' => 'sanitize_key' ), 'value' => array( 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/record/(?P<type>[a-z0-9-]+)/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_record' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/network/(?P<scheme>[a-z0-9-]+)/(?P<value>[^/]+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_network' ) ) );
    }

    public function rest_capabilities() {
        return rest_ensure_response( array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'contract' => self::contract(), 'schemes' => self::scheme_registry(), 'resolve_url' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/resolve' ) ) );
    }
    public function rest_schemes() { return rest_ensure_response( array( 'schema' => self::SCHEMA, 'schemes' => self::scheme_registry() ) ); }
    public function rest_resolve( WP_REST_Request $request ) { $r = self::resolve_identifier( $request->get_param( 'scheme' ), $request->get_param( 'value' ) ); return is_wp_error( $r ) ? $r : rest_ensure_response( $r ); }
    public function rest_record( WP_REST_Request $request ) { $r = self::record_identity( $request['type'], $request['id'] ); return is_wp_error( $r ) ? $r : rest_ensure_response( $r ); }
    public function rest_network( WP_REST_Request $request ) { $r = self::network_for_identifier( $request['scheme'], rawurldecode( (string) $request['value'] ) ); return is_wp_error( $r ) ? $r : rest_ensure_response( $r ); }

    public function cors_headers( $response, $server, $request ) {
        if ( ! $request instanceof WP_REST_Request || 0 !== strpos( $request->get_route(), '/' . self::REST_NAMESPACE . self::REST_ROUTE ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
        $allowed = class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::allowed_origins() : array();
        if ( $origin && in_array( $origin, $allowed, true ) && method_exists( $response, 'header' ) ) {
            $response->header( 'Access-Control-Allow-Origin', $origin );
            $response->header( 'Access-Control-Allow-Credentials', 'false' );
            $response->header( 'Access-Control-Allow-Methods', 'GET' );
            $response->header( 'Vary', 'Origin' );
        }
        return $response;
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Research Identity, Authority & Persistent Identifier Network', 'sustainable-catalyst-library' ) ), $atts, 'sc_research_identity_authority' );
        wp_enqueue_style( 'sc-library-research-identity-v520' ); wp_enqueue_script( 'sc-library-research-identity-v520' );
        $config = array( 'resolveUrl' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/resolve' ), 'networkBase' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/network/' ), 'schemes' => self::scheme_registry() );
        ob_start(); ?>
        <section class="sc-research-identity" data-sc-research-identity data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
            <header><p class="sc-research-identity__kicker"><?php esc_html_e( 'Authority & persistent identifiers', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><p><?php esc_html_e( 'Resolve explicit DOI, ORCID, ROR, ISBN, ISSN, Wikidata and PMID identifiers across public Sustainable Catalyst records and explicitly published federation metadata. Matches preserve provenance and ambiguity; they are not automatic identity, authorship, affiliation, truth, or access determinations.', 'sustainable-catalyst-library' ); ?></p></header>
            <form class="sc-research-identity__search" data-identity-form role="search">
                <label><span><?php esc_html_e( 'Identifier type', 'sustainable-catalyst-library' ); ?></span><select data-identity-scheme><?php foreach ( self::scheme_registry() as $key => $profile ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $profile['label'] ); ?></option><?php endforeach; ?></select></label>
                <label class="sc-research-identity__value"><span><?php esc_html_e( 'Persistent identifier', 'sustainable-catalyst-library' ); ?></span><input data-identity-value type="search" required minlength="2" autocomplete="off" placeholder="10.1234/example or 0000-0002-1825-0097"></label>
                <button type="submit"><?php esc_html_e( 'Resolve identifier', 'sustainable-catalyst-library' ); ?></button>
            </form>
            <div class="sc-research-identity__status" data-identity-status aria-live="polite"><?php esc_html_e( 'Enter an identifier to inspect its normalized form, validation state, public record matches, federation lineage, and ambiguity.', 'sustainable-catalyst-library' ); ?></div>
            <div class="sc-research-identity__results" data-identity-results></div>
            <footer><p><?php esc_html_e( 'Validation is local syntax/checksum validation. Sustainable Catalyst does not contact external registries during resolution and does not automatically merge records.', 'sustainable-catalyst-library' ); ?></p></footer>
        </section><?php return ob_get_clean();
    }
}
