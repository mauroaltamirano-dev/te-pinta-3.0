const root = '/admin/marketing/instagram';

export const instagramRoutes = {
    index: root,
    automation: `${root}/automation`,
    schedules: `${root}/schedules`,
    schedule: (id: number) => `${root}/schedules/${id}`,
    assets: `${root}/assets`,
    asset: (id: number) => `${root}/assets/${id}`,
    publications: `${root}/publications`,
    publication: (id: number) => `${root}/publications/${id}`,
    archivePublication: (id: number) => `${root}/publications/${id}/archive`,
    retryPublication: (id: number) => `${root}/publications/${id}/retry`,
    cancelPublication: (id: number) => `${root}/publications/${id}/cancel`,
    archiveHistory: `${root}/publications/archive-old`,
} as const;
