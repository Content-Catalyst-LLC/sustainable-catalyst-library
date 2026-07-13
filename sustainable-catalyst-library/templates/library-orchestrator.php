<section class="sc-orchestrator<?php echo $orchestrator_compact ? ' is-compact' : ''; ?>" data-sc-library-orchestrator data-initial-intent="<?php echo esc_attr($orchestrator_intent); ?>" data-initial-record="<?php echo esc_attr($orchestrator_record); ?>">
    <header class="sc-orchestrator__header">
        <p class="sc-library__eyebrow"><?php esc_html_e('Site-scoped research guidance', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($orchestrator_title); ?></h2>
        <p><?php echo esc_html($orchestrator_intro); ?></p>
    </header>
    <form class="sc-orchestrator__form" data-orchestrator-form>
        <label class="sc-orchestrator__question">
            <span><?php esc_html_e('Research question or task', 'sustainable-catalyst-library'); ?></span>
            <textarea name="prompt" rows="4" required maxlength="1200" placeholder="<?php esc_attr_e('What are you trying to understand, collect, analyze, compare, map, calculate, review, or publish?', 'sustainable-catalyst-library'); ?>"></textarea>
        </label>
        <div class="sc-orchestrator__controls">
            <label><span><?php esc_html_e('Research intent', 'sustainable-catalyst-library'); ?></span><select name="intent"></select></label>
            <label><span><?php esc_html_e('Maximum records', 'sustainable-catalyst-library'); ?></span><select name="max_records"><option>5</option><option selected>8</option><option>12</option><option>16</option></select></label>
            <button type="submit" class="is-primary"><?php esc_html_e('Build Research Route', 'sustainable-catalyst-library'); ?></button>
        </div>
    </form>
    <div class="sc-orchestrator__notice" data-orchestrator-notice hidden></div>
    <div class="sc-orchestrator__output" data-orchestrator-output>
        <div class="sc-orchestrator__empty">
            <strong><?php esc_html_e('The Research Librarian is ready.', 'sustainable-catalyst-library'); ?></strong>
            <p><?php esc_html_e('It searches the Sustainable Catalyst Library and Knowledge Graph, then proposes transparent, user-confirmed workspace actions.', 'sustainable-catalyst-library'); ?></p>
        </div>
    </div>
</section>
