import axios, { AxiosError } from 'axios';

/** Axios instance for JSON endpoints. Laravel's XSRF-TOKEN cookie is sent automatically. */
export const http = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withCredentials: true,
});

/** Extract a human-readable message from a failed request. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    if (error instanceof AxiosError) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        if (data?.errors) {
            const first = Object.values(data.errors)[0];
            if (first?.[0]) return first[0];
        }
        if (error.response?.status === 419) return 'Your session expired. Reload the page and try again.';
        if (error.response?.status === 429) return data?.message || 'Too many requests. Please wait a moment.';
        if (data?.message && error.response && error.response.status < 500) return data.message;
        if (data?.message && error.response?.status === 503) return data.message;
        if (!error.response) return 'Network error. Check your connection and try again.';
    }
    return fallback;
}

export function fieldErrors(error: unknown): Record<string, string> {
    if (error instanceof AxiosError) {
        const errs = (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors;
        if (errs) return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
    }
    return {};
}
