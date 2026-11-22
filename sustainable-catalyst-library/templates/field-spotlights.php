<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$instance_id = wp_unique_id( 'sc-field-stack-' );
$field_list = array_values( $fields );
$requested_field = isset( $_GET['sc_publication_field'] ) ? sanitize_title( (string) wp_unslash( $_GET['sc_publication_field'] ) ) : '';
$total_panels = 0;
foreach ( $field_list as $candidate_field ) {
    $total_panels += is_array( $candidate_field['panels'] ?? null ) ? count( $candidate_field['panels'] ) : 0;
}

// Interest paths are editorial entry points to the *existing* public field
// model. Direct article links are taken only from the verified directory.
$interest_prompts = array(
    'Sustainable Systems' => array(
        'question' => 'How can societies live within environmental limits?',
        'lead' => 'Explore sustainability, planetary boundaries, climate risk, and the systems connecting them.',
        'theme' => 'SUSTAINABILITY',
    ),
    'Technology & Systems Intelligence' => array(
        'question' => 'How are AI, data, and infrastructure changing decisions?',
        'lead' => 'Understand artificial intelligence, data systems, monitoring, and connected infrastructure.',
        'theme' => 'TECHNOLOGY',
    ),
    'Natural Science' => array(
        'question' => 'What does science tell us about the natural world?',
        'lead' => 'Follow research foundations across physics, biology, chemistry, Earth systems, and more.',
        'theme' => 'SCIENCE',
    ),
    'Global Governance' => array(
        'question' => 'How do law and institutions shape global cooperation?',
        'lead' => 'Examine international law, governance, legal traditions, and shared institutions.',
        'theme' => 'GOVERNANCE',
    ),
    'Psychology' => array(
        'question' => 'Why do people think, decide, and behave as they do?',
        'lead' => 'Explore cognition, social behavior, development, well-being, and organizational life.',
        'theme' => 'HUMAN BEHAVIOR',
    ),
    'Problem Solving' => array(
        'question' => 'How can we understand and solve complex problems?',
        'lead' => 'Work through decisions, modeling, strategy, systems methods, and practical tools.',
        'theme' => 'METHODS',
    ),
);
$interest_cards = array();
foreach ( $field_list as $entry_field ) {
    $entry_title = (string) ( $entry_field['title'] ?? '' );
    if ( ! isset( $interest_prompts[ $entry_title ] ) ) { continue; }
    $entry_key = sanitize_title( (string) ( $entry_field['key'] ?? '' ) );
    $entry = $interest_prompts[ $entry_title ];
    $entry['field'] = $entry_field;
    $entry['field_key'] = $entry_key;
    $entry['target'] = $instance_id . '-' . sanitize_html_class( $entry_key );
    // This map has already passed published/public/unprotected permalink
    // verification in the existing Publications directory classifier.
    $entry['map'] = ! empty( $publications_directory[ $entry_key ]['maps'][0] )
        ? $publications_directory[ $entry_key ]['maps'][0] : null;
    $interest_cards[ $entry_title ] = $entry;
}
$interest_cards = array_replace(
    array_intersect_key( $interest_prompts, $interest_cards ),
    $interest_cards
);
$interest_cards = array_filter( $interest_cards, static fn( $entry ) => isset( $entry['field'] ) );
?>
<div id="<?php echo esc_attr( $instance_id ); ?>" class="sc-field-stack" data-sc-field-stack="v4.3.22.4" data-sc-field-stack-mode="all-fields" aria-label="<?php esc_attr_e( 'Publications by major field', 'sustainable-catalyst-library' ); ?>">
    <section class="sc-pubintent" aria-labelledby="<?php echo esc_attr( $instance_id . '-interests' ); ?>" data-sc-publications-interest-entry>
        <div class="sc-pubintent__head">
            <div>
                <p class="sc-pubintent__eyebrow"><?php esc_html_e( 'DISCOVER / BY INTEREST', 'sustainable-catalyst-library' ); ?></p>
                <h3 id="<?php echo esc_attr( $instance_id . '-interests' ); ?>"><?php esc_html_e( 'What would you like to understand?', 'sustainable-catalyst-library' ); ?></h3>
            </div>
            <p><?php esc_html_e( 'Start with a question. Each pathway connects to existing publications and Article Maps, with the complete field collection available below.', 'sustainable-catalyst-library' ); ?></p>
        </div>
        <div class="sc-pubintent__grid">
            <?php foreach ( $interest_cards as $interest ) : ?>
                <article class="sc-pubintent__card">
                    <span class="sc-pubintent__tag"><?php echo esc_html( $interest['theme'] ); ?></span>
                    <h4><?php echo esc_html( $interest['question'] ); ?></h4>
                    <p><?php echo esc_html( $interest['lead'] ); ?></p>
                    <?php if ( ! empty( $interest['map']['url'] ) && ! empty( $interest['map']['title'] ) ) : ?>
                        <a class="sc-pubintent__primary" href="<?php echo esc_url( $interest['map']['url'] ); ?>"><?php echo esc_html( sprintf( __( 'Read: %s', 'sustainable-catalyst-library' ), $interest['map']['title'] ) ); ?> <span aria-hidden="true">&nearr;</span></a>
                    <?php endif; ?>
                    <a class="sc-pubintent__field" href="#<?php echo esc_attr( $interest['target'] ); ?>"><?php echo esc_html( sprintf( __( 'Explore %s', 'sustainable-catalyst-library' ), $interest['field']['title'] ) ); ?> <span aria-hidden="true">&#8595;</span></a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="sc-pubintent__more">
            <p><?php esc_html_e( 'Looking for a different subject?', 'sustainable-catalyst-library' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/feature-suggestions/' ) ); ?>"><?php esc_html_e( 'Request a topic', 'sustainable-catalyst-library' ); ?> <span aria-hidden="true">&nearr;</span></a>
        </div>
    </section>
    <details class="sc-pubintent__field-directory" <?php echo $requested_field ? 'open' : ''; ?> data-sc-publications-field-disclosure>
        <summary>
            <span><?php esc_html_e( 'Browse all fields and Article Maps', 'sustainable-catalyst-library' ); ?></span>
            <small><?php echo esc_html( sprintf( __( '%d major fields · %d Article Maps', 'sustainable-catalyst-library' ), count( $field_list ), $total_panels ) ); ?></small>
            <span aria-hidden="true">+</span>
        </summary>
    <section class="sc-field-stack__index" aria-labelledby="<?php echo esc_attr( $instance_id . '-title' ); ?>">
        <div class="sc-field-stack__index-head">
            <div>
                <p class="sc-field-stack__eyebrow"><?php echo esc_html( sprintf( '%d MAJOR FIELDS · %d ARTICLE MAPS', count( $field_list ), $total_panels ) ); ?></p>
                <h3 id="<?php echo esc_attr( $instance_id . '-title' ); ?>">Publications by field</h3>
            </div>
            <p>All major fields are rendered below. Use this index to jump to a field, or scroll through the complete Publications architecture.</p>
        </div>
        <nav class="sc-field-stack__jump-grid" aria-label="<?php esc_attr_e( 'Jump to a Publications field', 'sustainable-catalyst-library' ); ?>">
            <?php foreach ( $field_list as $index => $selector_field ) : ?>
                <?php
                $field_key = sanitize_title( (string) ( $selector_field['key'] ?? '' ) );
                $field_anchor = $instance_id . '-' . sanitize_html_class( $field_key );
                $is_requested = $requested_field && $requested_field === $field_key;
                ?>
                <a class="sc-field-stack__jump<?php echo $is_requested ? ' is-requested' : ''; ?>" href="#<?php echo esc_attr( $field_anchor ); ?>">
                    <span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                    <strong><?php echo esc_html( (string) $selector_field['title'] ); ?></strong>
                    <small><?php echo esc_html( (string) count( $selector_field['panels'] ?? array() ) ); ?></small>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>
    </details>

    <?php if ( ! empty( $publications_directory ) ) {
        include SC_LIBRARY_DIR . 'templates/publications-directory.php';
    } ?>

    <details class="sc-pubintent__full-collection" <?php echo $requested_field ? 'open' : ''; ?> data-sc-publications-full-collection>
        <summary>
            <span><?php esc_html_e( 'Explore the complete field collection', 'sustainable-catalyst-library' ); ?></span>
            <small><?php esc_html_e( '14 fields · guided Article Map spotlights', 'sustainable-catalyst-library' ); ?></small>
            <span aria-hidden="true">+</span>
        </summary>
    <div class="sc-field-stack__fields">
        <?php foreach ( $field_list as $index => $stack_field ) : ?>
            <?php
            $field = $stack_field;
            $field_number = $index + 1;
            $field_key = sanitize_title( (string) ( $field['key'] ?? '' ) );
            $stage_id = $instance_id . '-' . sanitize_html_class( $field_key );
            $master_stage = false;
            $include_data = true;
            ?>
            <div class="sc-field-spotlights sc-field-spotlights--single sc-field-spotlights--stack-item<?php echo $requested_field === $field_key ? ' is-requested' : ''; ?>" data-sc-field-spotlights="v4.3.22.4" data-sc-field-spotlights-mode="single" data-sc-field-spotlights-runtime-state="server" data-stack-field-key="<?php echo esc_attr( $field_key ); ?>">
                <?php include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
    </details>
</div>
