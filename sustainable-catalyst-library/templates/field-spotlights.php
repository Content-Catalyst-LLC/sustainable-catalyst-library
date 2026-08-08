<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-spotlights-' );
$field_list = array_values( $fields );
$initial_field = $field_list[0];
$initial_number = 1;
$stage_id = $instance_id . '-stage';
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-spotlights sc-field-spotlights--master" data-sc-field-spotlights="v4.3.13" data-sc-field-spotlights-mode="master" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr( (string) $interval ); ?>" data-pause-on-hover="<?php echo $pause_on_hover ? 'true' : 'false'; ?>">
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
                <button type="button" role="tab" class="sc-field-master__field-tab<?php echo 0 === $index ? ' is-active' : ''; ?>" data-field-select-key="<?php echo esc_attr( (string) $selector_field['key'] ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $stage_id ); ?>" tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                    <strong><?php echo esc_html( (string) $selector_field['title'] ); ?></strong>
                    <small><?php echo esc_html( (string) count( $selector_field['panels'] ) ); ?></small>
                </button>
            <?php endforeach; ?>
        </div>
        <label class="sc-field-master__mobile-select">
            <span>Choose a field</span>
            <select data-field-select aria-label="Choose a publication field">
                <?php foreach ( $field_list as $index => $selector_field ) : ?>
                    <option value="<?php echo esc_attr( (string) $selector_field['key'] ); ?>"<?php selected( 0, $index ); ?>><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) . ' · ' . (string) $selector_field['title'] . ' · ' . count( $selector_field['panels'] ) . ' panels' ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
