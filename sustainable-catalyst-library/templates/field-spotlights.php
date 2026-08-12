<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-spotlights-' );
$field_list = array_values( $fields );
$requested_field = isset( $_GET['sc_publication_field'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publication_field'] ) ) : '';
$initial_index = 0;
if ( $requested_field ) {
    foreach ( $field_list as $index => $candidate_field ) {
        if ( sanitize_title( (string) ( $candidate_field['key'] ?? '' ) ) === $requested_field ) { $initial_index = $index; break; }
    }
}
$initial_field = $field_list[ $initial_index ];
$initial_number = $initial_index + 1;
$stage_id = $instance_id . '-stage';
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-spotlights sc-field-spotlights--master" data-sc-field-spotlights="v4.3.22.1" data-sc-field-spotlights-mode="master" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr( (string) $interval ); ?>" data-pause-on-hover="<?php echo $pause_on_hover ? 'true' : 'false'; ?>" data-initial-field-key="<?php echo esc_attr( (string) $initial_field['key'] ); ?>">
    <section class="sc-field-master__selector" aria-labelledby="<?php echo esc_attr( $instance_id . '-selector-title' ); ?>">
        <div class="sc-field-master__selector-head">
            <div>
                <p class="sc-field-master__eyebrow">14 MAJOR FIELDS</p>
                <h3 id="<?php echo esc_attr( $instance_id . '-selector-title' ); ?>">Choose a field</h3>
            </div>
            <p>Switch fields without leaving the Spotlight. Each selection loads its Article Maps and curated publications into the shared stage below.</p>
        </div>
        <div class="sc-field-master__field-tabs" role="tablist" aria-label="Publication fields">
            <?php foreach ( $field_list as $index => $selector_field ) : ?>
                <?php $field_href = add_query_arg( array( 'sc_publication_field' => (string) $selector_field['key'] ), remove_query_arg( 'sc_publication_panel' ) ) . '#' . $stage_id; ?>
                <a role="tab" class="sc-field-master__field-tab<?php echo $initial_index === $index ? ' is-active' : ''; ?>" href="<?php echo esc_url( $field_href ); ?>" data-field-select-key="<?php echo esc_attr( (string) $selector_field['key'] ); ?>" aria-selected="<?php echo $initial_index === $index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $stage_id ); ?>" tabindex="<?php echo $initial_index === $index ? '0' : '-1'; ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                    <strong><?php echo esc_html( (string) $selector_field['title'] ); ?></strong>
                    <small><?php echo esc_html( (string) count( $selector_field['panels'] ) ); ?></small>
                </a>
            <?php endforeach; ?>
        </div>
        <label class="sc-field-master__mobile-select">
            <span>Choose a field</span>
            <select data-field-select aria-label="Choose a publication field">
                <?php foreach ( $field_list as $index => $selector_field ) : ?>
                    <option value="<?php echo esc_attr( (string) $selector_field['key'] ); ?>"<?php selected( $initial_index, $index ); ?>><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) . ' · ' . (string) $selector_field['title'] . ' · ' . count( $selector_field['panels'] ) . ' panels' ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><style>
            #<?php echo esc_attr( $instance_id ); ?> .sc-field-master__field-tabs{display:grid!important}
            #<?php echo esc_attr( $instance_id ); ?> .sc-field-master__mobile-select{display:none!important}
            #<?php echo esc_attr( $instance_id ); ?> [data-additional-tabs][hidden]{display:grid!important}
            #<?php echo esc_attr( $instance_id ); ?> [data-more-toggle]{display:none!important}
        </style></noscript>
    </section>

    <?php
    $field = $initial_field;
    $field_number = $initial_number;
    $master_stage = true;
    $include_data = false;
    include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php';
    ?>

    <script type="application/json" class="sc-field-spotlights__master-data"><?php echo wp_json_encode( array( 'fields' => $field_list, 'labels' => $labels ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
