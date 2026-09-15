<style>
    .profile-page {
        --profile-card-radius: 0.75rem;
        --profile-header-min-height: 56px;
        --profile-header-padding-y: 0.75rem;
        --profile-header-padding-x: 1rem;
        --profile-card-body-padding: 1.25rem;
        --profile-title-size: 1rem;
        --profile-field-label-size: 0.75rem;
        --profile-field-value-size: 0.95rem;
        --profile-icon-size: 1.25rem;
        --profile-avatar-size: 220px;
        --profile-avatar-placeholder-size: 5rem;
    }

    .profile-page .card {
        border-radius: var(--profile-card-radius);
    }

    .profile-page .card-header {
        min-height: var(--profile-header-min-height);
        padding: var(--profile-header-padding-y) var(--profile-header-padding-x);
        display: flex;
        align-items: center;
    }

    .profile-page .card-header h5 {
        margin: 0;
        font-size: var(--profile-title-size);
        font-weight: 600;
        line-height: 1.2;
    }

    .profile-page .card-body {
        padding: var(--profile-card-body-padding);
    }

    .profile-page .form-label.small,
    .profile-page .form-label.text-muted.small {
        font-size: var(--profile-field-label-size);
        margin-bottom: 0.25rem;
    }

    .profile-page p.mb-0 {
        font-size: var(--profile-field-value-size);
        line-height: 1.4;
    }

    .profile-page .profile-icon,
    .profile-page .input-group-text .profile-icon {
        font-size: var(--profile-icon-size);
        line-height: 1;
    }

    .profile-page .profile-avatar {
        width: var(--profile-avatar-size);
        height: var(--profile-avatar-size);
        object-fit: cover;
        border-radius: 50%;
    }

    .profile-page .profile-avatar-wrap {
        width: var(--profile-avatar-size);
        height: var(--profile-avatar-size);
        border-radius: 50%;
        background: var(--app-surface-muted);
        border: 1px solid var(--app-border);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .profile-page .profile-avatar-placeholder {
        font-size: var(--profile-avatar-placeholder-size);
        color: var(--bs-secondary-color);
    }

    .profile-page .btn i {
        font-size: 1rem;
        line-height: 1;
        vertical-align: -0.125em;
    }

    /* Tab navigation */
    .profile-page .profile-tabs {
        border-bottom: 2px solid var(--app-border);
    }

    .profile-page .profile-tabs .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--bs-secondary-color);
        font-weight: 500;
        padding: 0.625rem 1.25rem;
        transition: color 0.15s, border-color 0.15s;
    }

    .profile-page .profile-tabs .nav-link:hover {
        color: var(--bs-primary);
        border-bottom-color: rgba(var(--bs-primary-rgb), 0.3);
    }

    .profile-page .profile-tabs .nav-link.active {
        color: var(--bs-primary, #0d6efd);
        border-bottom-color: var(--bs-primary, #0d6efd);
        background: transparent;
    }

    .profile-page .tab-pane {
        padding-top: 1.5rem;
    }

    /* Profile completion progress */
    .profile-page .completion-bar {
        height: 8px;
        border-radius: 4px;
        background: var(--bs-secondary-bg);
        overflow: hidden;
    }

    .profile-page .completion-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s ease;
    }

    .profile-page .completion-pct {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
    }

    /* Profile header card */
    .profile-page .profile-header-card {
        background: linear-gradient(135deg, var(--app-surface-muted) 0%, var(--app-surface) 100%);
        border: none;
    }

    .profile-page .profile-header-card .profile-avatar,
    .profile-page .profile-header-card .profile-avatar-wrap {
        width: 120px;
        height: 120px;
    }

    .profile-page .profile-header-card .profile-avatar-placeholder {
        font-size: 3rem;
    }

    /* Parish affiliation list */
    .profile-page .affiliation-list dt {
        font-size: var(--profile-field-label-size);
        color: var(--bs-secondary-color);
        font-weight: 500;
    }

    .profile-page .affiliation-list dd {
        font-size: var(--profile-field-value-size);
        margin-bottom: 0.5rem;
    }

    /* Edit page section spacing */
    .profile-page .form-section-card {
        margin-bottom: 1rem;
    }
</style>