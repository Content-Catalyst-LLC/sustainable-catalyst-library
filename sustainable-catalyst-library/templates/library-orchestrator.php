<section class="sc-orchestrator<?php echo $orchestrator_compact ? ' is-compact' : ''; ?><?php echo $orchestrator_front_door ? ' is-front-door' : ''; ?>" data-sc-library-orchestrator data-initial-intent="<?php echo esc_attr($orchestrator_intent); ?>" data-initial-record="<?php echo esc_attr($orchestrator_record); ?>" data-initial-record-ids="<?php echo esc_attr(implode(',', $orchestrator_record_ids)); ?>" data-orchestrator-mode="<?php echo esc_attr($orchestrator_mode); ?>" data-full-url="<?php echo esc_attr($orchestrator_full_url); ?>" data-library-url="<?php echo esc_attr($orchestrator_library_url); ?>">
    <header class="sc-orchestrator__header">
        <p class="sc-library__eyebrow"><?php echo $orchestrator_front_door ? esc_html__('Guided research', 'sustainable-catalyst-library') : esc_html__('Site-scoped research guidance', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($orchestrator_title); ?></h2>
        <p><?php echo esc_html($orchestrator_intro); ?></p>
    </header>
    <form class="sc-orchestrator__form" data-orchestrator-form>
        <label class="sc-orchestrator__question">
            <span><?php echo $orchestrator_front_door ? esc_html__('What are you researching?', 'sustainable-catalyst-library') : esc_html__('Research question or task', 'sustainable-catalyst-library'); ?></span>
            <textarea name="prompt" rows="<?php echo $orchestrator_front_door ? '3' : '4'; ?>" required maxlength="1200" placeholder="<?php echo esc_attr($orchestrator_placeholder); ?>"><?php echo esc_textarea($orchestrator_initial_prompt); ?></textarea>
        </label>
        <?php if ($orchestrator_front_door) : ?>
            <input type="hidden" name="intent" value="<?php echo esc_attr($orchestrator_intent); ?>">
            <input type="hidden" name="max_records" value="5">
            <div class="sc-orchestrator__front-door-actions">
                <button type="submit" class="is-primary"><?php echo esc_html($orchestrator_button_label ?: __('Ask the Research Librarian', 'sustainable-catalyst-library')); ?></button>
                <?php if ($orchestrator_full_url) : ?>
                    <a class="sc-orchestrator__text-link" href="<?php echo esc_url($orchestrator_full_url); ?>"><?php esc_html_e('Open the full Research Librarian →', 'sustainable-catalyst-library'); ?></a>
                <?php endif; ?>
            </div>
            <?php if ($orchestrator_examples) : ?>
                <div class="sc-orchestrator__examples" aria-label="<?php esc_attr_e('Example research questions', 'sustainable-catalyst-library'); ?>">
                    <span><?php esc_html_e('Try:', 'sustainable-catalyst-library'); ?></span>
                    <?php foreach (array_slice($orchestrator_examples, 0, 4) as $example) : ?>
                        <button type="button" class="sc-orchestrator__example" data-orchestrator-example="<?php echo esc_attr($example); ?>"><?php echo esc_html($example); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="sc-orchestrator__controls">
                <label><span><?php esc_html_e('Research intent', 'sustainable-catalyst-library'); ?></span><select name="intent"></select></label>
                <label><span><?php esc_html_e('Maximum records', 'sustainable-catalyst-library'); ?></span><select name="max_records"><option>5</option><option selected>8</option><option>12</option><option>16</option></select></label>
                <button type="submit" class="is-primary"><?php echo esc_html($orchestrator_button_label); ?></button>
            </div>
        <?php endif; ?>
    </form>
    <div class="sc-orchestrator__notice" data-orchestrator-notice hidden></div>
    <div class="sc-orchestrator__output" data-orchestrator-output>
        <div class="sc-orchestrator__empty">
            <strong><?php echo $orchestrator_front_door ? esc_html__('Ask the Research Librarian.', 'sustainable-catalyst-library') : esc_html__('The Research Librarian is ready.', 'sustainable-catalyst-library'); ?></strong>
            <p><?php echo $orchestrator_front_door
                ? esc_html__('The Librarian searches Sustainable Catalyst records and relationships, explains why material was selected, and keeps workspace actions under your control.', 'sustainable-catalyst-library')
                : esc_html__('It searches the Sustainable Catalyst Library and Knowledge Graph, then proposes transparent, user-confirmed workspace actions.', 'sustainable-catalyst-library'); ?></p>
        </div>
    </div>
</section>
