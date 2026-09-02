export type InstagramPublicationStatus =
    'pending' | 'processing' | 'published' | 'failed' | 'cancelled';

export interface InstagramStoryAsset {
    id: number;
    name: string;
    category: string;
    mime_type: string;
    width: number;
    height: number;
    is_active: boolean;
    preview_url: string;
    in_use: boolean;
    can_delete: boolean;
    active_schedules_count?: number;
    active_schedule_references?: Array<{ id: number; name: string }>;
    schedules_count?: number;
    publications_count?: number;
}

export interface InstagramStorySchedule {
    id: number;
    name: string;
    time: string;
    days_of_week: number[];
    asset_id: number | null;
    is_active: boolean;
    sort_order?: number;
    next_run_at?: string | null;
    asset: InstagramStoryAsset | null;
}

export interface InstagramStoryPublication {
    id: number;
    scheduled_for: string;
    status: InstagramPublicationStatus;
    attempts: number;
    error_message: string | null;
    failure_code: string | null;
    requires_manual_review: boolean;
    published_at: string | null;
    processing_started_at?: string | null;
    failed_at?: string | null;
    cancelled_at?: string | null;
    archived_at?: string | null;
    is_retryable?: boolean;
    retryable?: boolean;
    can_retry?: boolean;
    can_cancel?: boolean;
    can_archive?: boolean;
    meta_container_id?: string | null;
    meta_media_id?: string | null;
    type?: 'manual' | 'automatic';
    asset: Pick<InstagramStoryAsset, 'id' | 'name' | 'preview_url'>;
    schedule: Pick<InstagramStorySchedule, 'id' | 'name'> | null;
}

export interface InstagramPublicationPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface InstagramHistoryFilters {
    status?: string;
    period?: string;
    search?: string;
    show_archived?: boolean;
}

export interface InstagramOperationalSummary {
    pending: number;
    processing: number;
    errors_today: number;
    last_published_at: string | null;
    last_automatic_published_at?: string | null;
}

export interface InstagramPageProps {
    automation: {
        available: boolean;
        enabled: boolean;
        effective_enabled: boolean;
        timezone: string;
        last_automatic_published_at?: string | null;
    };
    schedules: InstagramStorySchedule[];
    assets: InstagramStoryAsset[];
    publications: InstagramStoryPublication[];
    publication_filters?: InstagramHistoryFilters;
    publication_pagination?: InstagramPublicationPagination;
    operational_summary?: InstagramOperationalSummary;
    pending_publications_count?: number;
}
