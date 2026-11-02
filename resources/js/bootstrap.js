import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Helper function to extract cookie by name
export function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    return null;
}

// Request interceptor to ensure freshest CSRF/XSRF token is always sent
window.axios.interceptors.request.use((config) => {
    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf && !config.headers['X-XSRF-TOKEN']) {
        config.headers['X-XSRF-TOKEN'] = xsrf;
    }
    const metaCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaCsrf && !config.headers['X-CSRF-TOKEN']) {
        config.headers['X-CSRF-TOKEN'] = metaCsrf;
    }
    return config;
});
