// Assets grid: videos show a picture; the video loads only when Play is tapped.
// A video without a picture gets one: when its tile comes into view the browser reads a frame
// (one video at a time), shows it and saves it, so nobody loads that video again just to see it.
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

function play(tile) {
    const video = document.createElement('video');
    Object.assign(video, { src: tile.dataset.src, controls: true, autoplay: true, playsInline: true, preload: 'metadata' });
    const picture = tile.querySelector('img');
    if (picture) video.poster = picture.src; // the picture stays while the video loads
    video.className = 'absolute inset-0 w-full h-full object-cover bg-black';
    tile.replaceChildren(video);
}

function showPicture(tile, url) {
    const img = document.createElement('img');
    Object.assign(img, { src: url, alt: '' });
    img.className = 'absolute inset-0 w-full h-full object-cover';
    tile.querySelector('[data-placeholder]')?.remove();
    tile.prepend(img);
}

/** A frame ~1 s in (or a quarter in for short clips) as a JPEG, max 480 px wide; null if the browser cannot decode it */
function grabFrame(src) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        let timer;
        const done = (blob) => {
            clearTimeout(timer);
            video.removeAttribute('src');
            video.load(); // stop downloading
            resolve(blob);
        };
        timer = setTimeout(() => done(null), 25000);
        video.muted = true;
        video.preload = 'metadata';
        video.addEventListener('loadedmetadata', () => { video.currentTime = Math.min(1, (video.duration || 4) / 4); });
        video.addEventListener('seeked', () => {
            if (!video.videoWidth) return done(null);
            const scale = Math.min(1, 480 / video.videoWidth);
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(done, 'image/jpeg', 0.75);
        });
        video.addEventListener('error', () => done(null));
        video.src = src;
    });
}

const queue = [];
let busy = false;

async function next() {
    if (busy || !queue.length) return;
    busy = true;
    const tile = queue.shift();
    if (tile.isConnected && !tile.querySelector('video')) {
        const blob = await grabFrame(tile.dataset.src);
        if (blob && tile.isConnected && !tile.querySelector('video')) {
            showPicture(tile, URL.createObjectURL(blob));
            const body = new FormData();
            body.append('thumb', blob, 'thumb.jpg');
            fetch(tile.dataset.save, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }).catch(() => {});
        }
    }
    busy = false;
    next();
}

function init() {
    const tiles = document.querySelectorAll('[data-video-tile]');
    if (!tiles.length) return;
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-video-tile] [data-play]');
        if (button) play(button.closest('[data-video-tile]'));
    });
    const seen = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        seen.unobserve(entry.target);
        queue.push(entry.target);
        next();
    }), { rootMargin: '200px' });
    tiles.forEach((tile) => tile.dataset.save && seen.observe(tile));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
