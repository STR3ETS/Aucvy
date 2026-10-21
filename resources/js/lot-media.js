/**
 * Foto's beheren op een kavel die al live is (briefing sectie 4): toevoegen,
 * verwijderen en de omslag kiezen. Dezelfde endpoints als de wizard; de
 * server bewaakt dat alleen de eigenaar erbij kan.
 */
export default function lotMedia(lotId, initial) {
    return {
        lotId,
        media: initial,
        uploads: [],
        error: null,
        over: false,

        get busy() {
            return this.uploads.some((upload) => upload.pct < 100 && ! upload.error);
        },

        async add(files) {
            this.error = null;

            for (const file of files) {
                if (! file.type.startsWith('image/')) {
                    this.uploads.push({ name: file.name, pct: 100, error: 'Geen foto.' });
                    continue;
                }

                if (file.size > 10 * 1024 * 1024) {
                    this.uploads.push({ name: file.name, pct: 100, error: 'Groter dan 10 MB.' });
                    continue;
                }

                const upload = { name: file.name, pct: 0, error: null };
                this.uploads.push(upload);

                const body = new FormData();
                body.append('file', file);

                try {
                    const response = await this.send(`/api/drafts/${this.lotId}/media`, 'POST', body, (pct) => (upload.pct = pct));
                    this.media = response.all;
                    upload.pct = 100;
                } catch (message) {
                    upload.error = message;
                    upload.pct = 100;
                }
            }

            setTimeout(() => (this.uploads = this.uploads.filter((upload) => upload.error)), 1500);
        },

        async remove(item) {
            if (! confirm('Deze foto verwijderen?')) return;

            try {
                const response = await this.send(`/api/drafts/${this.lotId}/media/${item.id}`, 'DELETE');
                this.media = response.all;
            } catch (message) {
                this.error = message;
            }
        },

        async cover(item) {
            try {
                const response = await this.send(`/api/drafts/${this.lotId}/media/${item.id}/cover`, 'POST');
                this.media = response.all;
            } catch (message) {
                this.error = message;
            }
        },

        /** XMLHttpRequest, want fetch geeft geen uploadvoortgang. */
        send(url, method, body = null, onProgress = null) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(method, url);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=csrf-token]')?.content ?? '');

                if (onProgress) {
                    xhr.upload.addEventListener('progress', (event) => {
                        if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100));
                    });
                }

                xhr.addEventListener('load', () => {
                    let data = {};
                    try { data = JSON.parse(xhr.responseText); } catch (error) {}

                    if (xhr.status >= 200 && xhr.status < 300) return resolve(data);

                    reject(data.message ?? Object.values(data.errors ?? {}).flat()[0] ?? 'Er ging iets mis.');
                });
                xhr.addEventListener('error', () => reject('Geen verbinding.'));
                xhr.send(body);
            });
        },
    };
}
