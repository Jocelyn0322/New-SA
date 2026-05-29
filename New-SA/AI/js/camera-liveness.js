// camera-liveness.js — Camera control, face detection, and liveness checks.
// Depends on: state.js (refs), skin-analysis.js (analyzeSkinFromImage).

// ── Camera ──────────────────────────────────────────────────────
const startCamera = async () => {
    try {
        if (!hasShownNaturalLightReminder.value) {
            alert('💡 建議在自然光下拍攝，效果最好。\n⏰ 避免下午出油時段；建議洗臉後 30 分鐘、在相同條件下拍攝，結果更準確。');
            hasShownNaturalLightReminder.value = true;
        }
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: 'user' }
        });
        cameraActive.value = true;
        await nextTick();
        if (!video.value) {
            const domVideo = document.querySelector('video');
            if (!domVideo) throw new Error('視頻元素尚未渲染，請稍候再試');
            video.value = domVideo;
        }
        video.value.srcObject = stream;
        video.value.onloadedmetadata = () => { video.value.play().catch(() => {}); };
        stream.getTracks().forEach(track => {
            track.addEventListener('ended', () => { cameraActive.value = false; });
        });
    } catch (error) {
        let msg = '無法訪問相機: ';
        if (error.name === 'NotAllowedError')       msg += '請允許相機權限';
        else if (error.name === 'NotFoundError')    msg += '未找到相機設備';
        else if (error.name === 'NotReadableError') msg += '相機被其他應用占用';
        else if (error.name === 'OverconstrainedError') msg += '相機不支持請求的配置';
        else msg += error.message;
        alert(msg);
    }
};

const stopCamera = () => {
    if (video.value && video.value.srcObject) {
        video.value.srcObject.getTracks().forEach(track => track.stop());
        video.value.srcObject = null;
    }
    video.value = null;
    cameraActive.value = false;
};

document.addEventListener('visibilitychange', () => {
    if (!document.hidden && cameraActive.value && video.value) {
        const tracks = video.value.srcObject?.getTracks() ?? [];
        if (!tracks.length || tracks.every(t => t.readyState === 'ended')) {
            video.value.srcObject = null;
            cameraActive.value = false;
        }
    }
});

// ── Face detection (BlazeFace fallback) ─────────────────────────
const ensureFallbackFaceDetector = (() => {
    let blazefaceModel = null;
    let loadingPromise = null;

    return async () => {
        if (typeof window.FaceDetector === 'function') {
            return {
                detect: async (videoEl) => {
                    const fd = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 2 });
                    const faces = await fd.detect(videoEl);
                    return Array.isArray(faces) ? faces.map(f => ({ raw: f, boundingBox: f.boundingBox })) : [];
                }
            };
        }

        if (blazefaceModel) {
            return {
                detect: async (videoEl) => {
                    const preds = await blazefaceModel.estimateFaces(videoEl, false);
                    return Array.isArray(preds) ? preds.map(pred => {
                        const [x1, y1] = pred.topLeft    || [0, 0];
                        const [x2, y2] = pred.bottomRight || [0, 0];
                        return { raw: pred, boundingBox: { x: x1, y: y1, width: x2 - x1, height: y2 - y1 } };
                    }) : [];
                }
            };
        }

        if (!loadingPromise) {
            loadingPromise = (async () => {
                await new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.7.0/dist/tf.min.js';
                    s.async = true; s.onload = resolve; s.onerror = reject;
                    document.head.appendChild(s);
                });
                await new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface@0.0.7/dist/blazeface.min.js';
                    s.async = true; s.onload = resolve; s.onerror = reject;
                    document.head.appendChild(s);
                });
                if (typeof blazeface === 'undefined') throw new Error('BlazeFace library未正確載入');
                blazefaceModel = await blazeface.load();
            })();
        }

        await loadingPromise;
        return {
            detect: async (videoEl) => {
                const preds = await blazefaceModel.estimateFaces(videoEl, false);
                return Array.isArray(preds) ? preds.map(pred => {
                    const [x1, y1] = pred.topLeft    || [0, 0];
                    const [x2, y2] = pred.bottomRight || [0, 0];
                    return { raw: pred, boundingBox: { x: x1, y: y1, width: x2 - x1, height: y2 - y1 } };
                }) : [];
            }
        };
    };
})();

const detectFacesInFrame = async () => {
    if (!video.value) throw new Error('相機畫面尚未準備好');
    const detector = await ensureFallbackFaceDetector();
    let faces = [];
    try {
        const results = await detector.detect(video.value);
        faces = Array.isArray(results) ? results : [];
    } catch (err) {
        console.warn('face detector error:', err);
        faces = [];
    }
    if (!faces.length)  throw new Error('請先讓畫面中出現清楚的人臉，再進行拍照');
    if (faces.length > 1) throw new Error('畫面中偵測到多人，請只保留一張臉再拍照');
    const face = faces[0];
    if (!face || !face.boundingBox) throw new Error('人臉偵測失敗，請重新對準臉部');
    return face;
};

// ── Image capture ────────────────────────────────────────────────
const captureImage = async () => {
    if (!video.value)  video.value  = document.querySelector('video');
    if (!canvas.value) canvas.value = document.querySelector('canvas');
    if (!video.value || !canvas.value) { alert('相機未啟動或畫布未準備好'); return; }
    if (faceDetectionBusy.value) return;

    faceDetectionBusy.value = true;
    try {
        const face = await Promise.race([
            detectFacesInFrame(),
            new Promise((_, reject) => setTimeout(() => reject(new Error('人臉偵測逾時，請確認網路或改用手動模式')), 7000))
        ]);
        try {
            const ok = await checkObstacleAndLiveness(face);
            if (!ok) return;
            const challengeOk = await awaitUserChallenge(face).catch(chErr => {
                alert(chErr.message || '挑戰回應失敗，請重試');
                return false;
            });
            if (!challengeOk) return;
        } catch (checkErr) {
            alert(checkErr.message || '活體或遮擋檢測失敗');
            return;
        }
    } catch (error) {
        alert(`無法拍照：${error.message}`);
        return;
    } finally {
        faceDetectionBusy.value = false;
    }

    const ctx = canvas.value.getContext('2d');
    canvas.value.width  = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    ctx.save();
    ctx.translate(canvas.value.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
    ctx.restore();

    await analyzeSkinFromImage();
};

// ── Liveness helpers ─────────────────────────────────────────────
const isSkinPixel = (r, g, b) => {
    const y  = 0.299 * r + 0.587 * g + 0.114 * b;
    const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
    const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
    return (cb >= 70 && cb <= 135) && (cr >= 128 && cr <= 180) && y > 35;
};

const sampleFacePatch = (face, size = 128) => {
    const off = document.createElement('canvas');
    off.width = size; off.height = size;
    const ctx = off.getContext('2d');
    const bb  = face.boundingBox;
    ctx.drawImage(video.value,
        Math.max(0, Math.floor(bb.x)), Math.max(0, Math.floor(bb.y)),
        Math.max(1, Math.floor(bb.width)), Math.max(1, Math.floor(bb.height)),
        0, 0, size, size);
    return ctx.getImageData(0, 0, size, size);
};

// 額頭採樣：在 bounding box 上方 0.5 倍高度的區域取 128×48 patch
const sampleForeheadPatch = (face) => {
    const bb     = face.boundingBox;
    const fw     = Math.max(1, Math.floor(bb.width));
    const fh     = Math.max(1, Math.floor(bb.height));
    const pH     = Math.max(1, Math.floor(fh * 0.50));   // 取臉高的一半作為額頭採樣高度
    const pY     = Math.max(0, Math.floor(bb.y) - pH);   // 從 bounding box 上方開始
    const xPad   = Math.floor(fw * 0.20);
    const pX     = Math.max(0, Math.floor(bb.x) + xPad);
    const pW     = Math.max(1, fw - xPad * 2);
    const W = 128, H = 48;
    const off = document.createElement('canvas');
    off.width = W; off.height = H;
    const ctx = off.getContext('2d');
    ctx.drawImage(video.value, pX, pY, pW, pH, 0, 0, W, H);
    return ctx.getImageData(0, 0, W, H);
};

const computeSkinRatioFlat = (imageData) => {
    const { data, width, height } = imageData;
    let skinCount = 0, total = 0;
    for (let y = 0; y < height; y += 2) {
        for (let x = 0; x < width; x += 2) {
            const i = (y * width + x) * 4;
            if (isSkinPixel(data[i], data[i + 1], data[i + 2])) skinCount++;
            total++;
        }
    }
    return total > 0 ? (skinCount / total) : 0;
};

const computeSkinRatioRegion = (imageData, region = 'lower') => {
    const { data, width, height } = imageData;
    let skinCount = 0, total = 0;
    const mid = Math.floor(height * 0.5);
    const y0  = region === 'upper' ? 0   : mid;
    const y1  = region === 'upper' ? mid : height;
    // For lower region, only sample the center 60% horizontally to ignore side hair
    const xPad = region === 'lower' ? Math.floor(width * 0.20) : 0;
    for (let y = y0; y < y1; y += 3) {
        for (let x = xPad; x < width - xPad; x += 3) {
            const i = (y * width + x) * 4;
            if (isSkinPixel(data[i], data[i + 1], data[i + 2])) skinCount++;
            total++;
        }
    }
    return total > 0 ? (skinCount / total) : 0;
};

const computeLaplacianVariance = (imageData) => {
    const { data, width, height } = imageData;
    const gray = new Float32Array(width * height);
    for (let i = 0; i < width * height; i++) {
        const idx = i * 4;
        gray[i] = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
    }
    const lap = new Float32Array(width * height);
    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            const i = y * width + x;
            lap[i] = -4 * gray[i] + gray[i - 1] + gray[i + 1] + gray[i - width] + gray[i + width];
        }
    }
    let mean = 0, sq = 0, cnt = 0;
    for (let i = 0; i < lap.length; i++) { mean += lap[i]; sq += lap[i] * lap[i]; cnt++; }
    mean /= cnt;
    return sq / cnt - mean * mean;
};

const detectScreenMoireViaFFT = (imageData) => {
    const { data, width, height } = imageData;
    const cx = Math.floor(width / 2), cy = Math.floor(height / 2), regionSize = 64;
    const x0 = Math.max(0, cx - regionSize / 2), y0 = Math.max(0, cy - regionSize / 2);
    const gray = new Float32Array(regionSize * regionSize);
    for (let y = 0; y < regionSize; y++) {
        for (let x = 0; x < regionSize; x++) {
            const src = ((y0 + y) * width + (x0 + x)) * 4;
            gray[y * regionSize + x] = 0.299 * data[src] + 0.587 * data[src + 1] + 0.114 * data[src + 2];
        }
    }
    const freqStrength = {};
    for (let y = 0; y < regionSize; y++) {
        const row = new Float32Array(regionSize);
        for (let x = 0; x < regionSize; x++) row[x] = gray[y * regionSize + x];
        for (let lag = 1; lag <= regionSize / 4; lag++) {
            let sum = 0;
            for (let i = 0; i < regionSize - lag; i++) sum += row[i] * row[i + lag];
            const freq = Math.round(regionSize / lag);
            freqStrength[freq] = (freqStrength[freq] || 0) + Math.abs(sum);
        }
    }
    let moireScore = 0;
    for (let freq = 6; freq <= 20; freq++) { if (freqStrength[freq]) moireScore += freqStrength[freq]; }
    return moireScore;
};

const analyzeSkinTextureCoherence = (imageData) => {
    const { data, width, height } = imageData;
    const isSkin = (r, g, b) => {
        const y = 0.299 * r + 0.587 * g + 0.114 * b;
        const cb = 128 + 0.5 * (b - y), cr = 128 + 0.5 * (r - y);
        return y > 40 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173;
    };
    let coherenceScore = 0, sampleCount = 0;
    for (let attempt = 0; attempt < 20; attempt++) {
        const x = Math.floor(Math.random() * (width - 8));
        const y = Math.floor(Math.random() * (height - 8));
        const ci = (y * width + x) * 4;
        const r = data[ci], g = data[ci + 1], b = data[ci + 2];
        if (!isSkin(r, g, b)) continue;
        let variance = 0;
        for (let dy = -1; dy <= 1; dy++) {
            for (let dx = -1; dx <= 1; dx++) {
                if (dx === 0 && dy === 0) continue;
                const ni = ((y + dy) * width + (x + dx)) * 4;
                variance += Math.pow(data[ni] - r, 2) + Math.pow(data[ni + 1] - g, 2) + Math.pow(data[ni + 2] - b, 2);
            }
        }
        coherenceScore += variance;
        sampleCount++;
    }
    return sampleCount > 0 ? coherenceScore / sampleCount : 0;
};

const waitForFrames = (durationMs, intervalMs = 200, cb) => new Promise((resolve) => {
    const start = Date.now();
    const results = [];
    const tick = async () => {
        if (Date.now() - start >= durationMs) { resolve(results); return; }
        try { results.push(await cb()); } catch (e) { results.push(null); }
        setTimeout(tick, intervalMs);
    };
    tick();
});

// ── Debug panel ──────────────────────────────────────────────────
const ensureDebugPanel = () => {
    if (document.getElementById('liveness-debug')) return document.getElementById('liveness-debug');
    const d = document.createElement('div');
    d.id = 'liveness-debug';
    Object.assign(d.style, {
        position: 'fixed',
        left: '50%', bottom: '180px',
        transform: 'translateX(-50%)',
        zIndex: 99999,
        minWidth: '280px', maxWidth: '420px',
        background: 'rgba(0,0,0,0.72)',
        color: '#fff', fontSize: '14px',
        padding: '12px 20px', borderRadius: '14px',
        fontFamily: 'system-ui, -apple-system, "Helvetica Neue", Arial',
        textAlign: 'center', lineHeight: '1.6',
        backdropFilter: 'blur(4px)',
        pointerEvents: 'none',
    });
    d.innerHTML = '<div id="liveness-debug-body"></div>';
    document.body.appendChild(d);
    return d;
};

let _debugHideTimer = null;

const updateDebugPanel = (obj) => {
    const panel = ensureDebugPanel();
    const body = document.getElementById('liveness-debug-body');
    if (!body) return;

    const isLiveDefined = typeof obj.isLive !== 'undefined';
    const isPassed = isLiveDefined && obj.isLive === true;
    // 轉頭指示（非通過結果）才永久顯示
    const isInstruction = !!obj.instruction && !isPassed;
    const msg = obj.instruction || obj.reason || '';
    const icon = isLiveDefined ? (obj.isLive ? '✅' : '❌') : '';

    body.innerHTML = `
        ${icon ? `<div style="font-size:22px;margin-bottom:4px;">${icon}</div>` : ''}
        ${msg ? `<div style="font-size:14px;">${msg}</div>` : ''}
    `;

    panel.style.display = msg || icon ? 'block' : 'none';

    // 錯誤/狀態/通過提示 3 秒後消失；轉頭指示維持顯示
    if (_debugHideTimer) clearTimeout(_debugHideTimer);
    if (!isInstruction && (msg || icon)) {
        _debugHideTimer = setTimeout(() => {
            panel.style.display = 'none';
        }, 3000);
    }
};

// ── Head rotation detection ──────────────────────────────────────
const detectHeadRotation = async () => {
    updateDebugPanel({ instruction: '請依指示自然轉動頭部：向左→向右→點頭' });
    const measurements = [];

    const positions = await waitForFrames(4000, 150, async () => {
        try {
            let bgLum = null;
            try {
                if (canvas && canvas.value && canvas.value.getContext) {
                    const ctxMain = canvas.value.getContext('2d');
                    ctxMain.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
                    const w = Math.max(8, Math.floor(canvas.value.width * 0.08));
                    const h = Math.max(8, Math.floor(canvas.value.height * 0.08));
                    const p1 = ctxMain.getImageData(4, 4, w, h).data;
                    const p2 = ctxMain.getImageData(canvas.value.width - 4 - w, 4, w, h).data;
                    const avgLum = (arr) => {
                        let s = 0, cnt = 0;
                        for (let i = 0; i < arr.length; i += 4) {
                            s += 0.299 * arr[i] + 0.587 * arr[i + 1] + 0.114 * arr[i + 2]; cnt++;
                        }
                        return s / cnt;
                    };
                    bgLum = (avgLum(p1) + avgLum(p2)) / 2;
                }
            } catch (bgErr) { bgLum = null; }

            const face = await detectFacesInFrame().catch(() => null);
            if (!face || !face.boundingBox) return null;
            const bb = face.boundingBox;
            const centerX = bb.x + bb.width / 2, centerY = bb.y + bb.height / 2;
            const width = bb.width, height = bb.height;

            let noseRelX = null, noseRelY = null, eyeDistance = null;
            try {
                const lms = (face.raw || {}).landmarks || (face.raw || {}).landmark || null;
                if (Array.isArray(lms) && lms.length) {
                    const nose = lms[2] || lms[0], leftEye = lms[1] || lms[0], rightEye = lms[0] || lms[1];
                    noseRelX    = (nose[0] - bb.x) / bb.width;
                    noseRelY    = (nose[1] - bb.y) / bb.height;
                    eyeDistance = Math.hypot(leftEye[0] - rightEye[0], leftEye[1] - rightEye[1]);
                }
            } catch (lmErr) { /* ignore */ }

            measurements.push({ centerX, centerY, width, height, noseRelX, noseRelY, eyeDistance, bgLum });
            return { centerX, centerY, width, height, noseRelX, noseRelY, eyeDistance, bgLum };
        } catch (e) { return null; }
    });

    const validPositions = positions.filter(p => p !== null);
    if (validPositions.length < 5) {
        updateDebugPanel({ isLive: false, reason: '追蹤樣本太少，請將臉部置中並再試一次' });
        return { success: false, reason: '追蹤失敗' };
    }

    const smooth = (arr) => {
        if (arr.length < 3) return arr.slice();
        return arr.map((b, i) => ((arr[i - 1] ?? b) + b + (arr[i + 1] ?? b)) / 3);
    };

    const centerXValues = smooth(validPositions.map(p => p.centerX));
    const centerYValues = smooth(validPositions.map(p => p.centerY));
    const widthValues   = validPositions.map(p => p.width);

    const minX = Math.min(...centerXValues), maxX = Math.max(...centerXValues);
    const minY = Math.min(...centerYValues), maxY = Math.max(...centerYValues);
    const minW = Math.min(...widthValues),   maxW = Math.max(...widthValues);

    const horizontalMovement = maxX - minX;
    const verticalMovement   = maxY - minY;
    const sizeVariation      = maxW - minW;

    const xDeltas = centerXValues.slice(1).map((v, i) => v - centerXValues[i]);
    const yDeltas = centerYValues.slice(1).map((v, i) => v - centerYValues[i]);

    const calcVar = (arr) => {
        if (!arr.length) return 0;
        const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
        return arr.reduce((s, d) => s + Math.pow(d - mean, 2), 0) / arr.length;
    };
    const xDeltaVar = calcVar(xDeltas), yDeltaVar = calcVar(yDeltas);

    let xSignChanges = 0, ySignChanges = 0;
    const MIN_DELTA = 2;
    for (let i = 1; i < xDeltas.length; i++) {
        const a = Math.abs(xDeltas[i - 1]) > MIN_DELTA ? Math.sign(xDeltas[i - 1]) : 0;
        const b = Math.abs(xDeltas[i])     > MIN_DELTA ? Math.sign(xDeltas[i])     : 0;
        if (a !== 0 && b !== 0 && a !== b) xSignChanges++;
        const ya = Math.abs(yDeltas[i - 1]) > MIN_DELTA ? Math.sign(yDeltas[i - 1]) : 0;
        const yb = Math.abs(yDeltas[i])     > MIN_DELTA ? Math.sign(yDeltas[i])     : 0;
        if (ya !== 0 && yb !== 0 && ya !== yb) ySignChanges++;
    }

    const noseRelValues = validPositions.map(p => typeof p.noseRelX === 'number' ? p.noseRelX : null).filter(v => v !== null);
    const eyeDistValues = validPositions.map(p => typeof p.eyeDistance === 'number' ? p.eyeDistance : null).filter(v => v !== null);
    const bgLums        = validPositions.map(p => typeof p.bgLum === 'number' ? p.bgLum : null).filter(v => v !== null);

    const range = (arr) => arr.length ? Math.max(...arr) - Math.min(...arr) : 0;
    const noseRange    = range(noseRelValues);
    const eyeDistRange = range(eyeDistValues);

    const pearson = (a, b) => {
        if (!a.length || a.length !== b.length) return 0;
        const n = a.length;
        const meanA = a.reduce((s, v) => s + v, 0) / n;
        const meanB = b.reduce((s, v) => s + v, 0) / n;
        let num = 0, denA = 0, denB = 0;
        for (let i = 0; i < n; i++) {
            const da = a[i] - meanA, db = b[i] - meanB;
            num += da * db; denA += da * da; denB += db * db;
        }
        const den = Math.sqrt(denA * denB);
        return den ? num / den : 0;
    };

    let cameraMotion = false;
    try {
        if (bgLums.length >= 3) {
            const a = centerXValues.slice(-bgLums.length);
            const b = bgLums.slice(-a.length);
            const corr = pearson(a, b);
            const bgVar = calcVar(b);
            cameraMotion = Math.abs(corr) > 0.55 && bgVar > 1;
        }
    } catch (e) { cameraMotion = false; }

    const hasHorizontalMovement = horizontalMovement > 18;
    const hasVerticalMovement   = verticalMovement   > 12;
    const hasSizeChange         = sizeVariation      > 8;
    const isTooChaotic          = xDeltaVar > 1000 || yDeltaVar > 1000;
    const tooManyReversals      = (xSignChanges > 8 && horizontalMovement > 0) || (ySignChanges > 8 && verticalMovement > 0);

    const leftCount  = noseRelValues.filter(v => v < 0.45).length;
    const rightCount = noseRelValues.filter(v => v > 0.55).length;
    const crossedSides = leftCount >= 2 && rightCount >= 2;
    const noseEvidence = noseRelValues.length >= 3 && noseRange > 0.10 && crossedSides;
    const eyeEvidence  = eyeDistValues.length  >= 3 && eyeDistRange > 8;

    if (noseRelValues.length < 3) {
        updateDebugPanel({ isLive: false, reason: '無法取得鼻子特徵點，請正臉對準鏡頭並確保光線充足' });
        return { success: false, reason: '特徵點追蹤失敗' };
    }

    const coherentMovement = noseEvidence
        && (hasHorizontalMovement || hasVerticalMovement)
        && !isTooChaotic && !tooManyReversals && !cameraMotion
        && validPositions.length >= 5;

    const isLive = coherentMovement;

    if (isLive) {
        updateDebugPanel({ isLive: true, horizontalMovement, sizeVariation, noseRange, eyeDistRange, bgCorr: cameraMotion ? 1 : 0 });
        return { success: true, reason: '檢測到真實頭部運動' };
    } else {
        let reason = '請緩慢向左轉頭再向右轉頭';
        if (!crossedSides)    reason = `鼻子未跨越中線（左${leftCount}次/右${rightCount}次），請向左轉再向右轉`;
        if (noseRange <= 0.10) reason = `臉部特徵點移動幅度不足（${(noseRange * 100).toFixed(1)}%），請加大頭部轉動角度`;
        if (isTooChaotic)     reason = '運動過於混亂，請穩定後緩慢轉頭';
        if (tooManyReversals) reason = '方向變化太快，請慢慢左右轉頭';
        if (cameraMotion)     reason = '偵測到鏡頭晃動，請固定手機再試';
        updateDebugPanel({ isLive: false, reason, horizontalMovement, sizeVariation, noseRange, eyeDistRange, bgCorr: cameraMotion ? 1 : 0 });
        return { success: false, reason };
    }
};

// ── Blink waveform ───────────────────────────────────────────────
const detectBlinkWaveform = (brightnessSamples) => {
    const data = brightnessSamples.filter(v => typeof v === 'number');
    if (data.length < 5) return { hasBlink: false, peaks: 0, reason: '樣本太少' };

    const derivatives = [];
    for (let i = 1; i < data.length; i++) derivatives.push(data[i] - data[i - 1]);

    const peaks = [];
    for (let i = 1; i < derivatives.length; i++) {
        if (derivatives[i - 1] > 0.5 && derivatives[i] < -0.5) peaks.push(i);
    }

    const minVal = Math.min(...data), maxVal = Math.max(...data);
    const range  = maxVal - minVal;

    if (range < 2) return { hasBlink: false, peaks: peaks.length, reason: '❌ 亮度幾乎無變化\n這是靜止照片的特徵 - 無法使用照片拍攝' };
    if (peaks.length > 5) return { hasBlink: false, peaks: peaks.length, reason: '❌ 亮度波動異常\n檢測到照片、螢幕或低品質圖像特徵' };
    if (range >= 2 && peaks.length <= 4) return { hasBlink: true, peaks: peaks.length, reason: '檢測到真實眨眼波形' };
    return { hasBlink: false, peaks: peaks.length, reason: '❌ 波形不符合活體驗證\n💡 提示：請勿使用照片或預錄視頻\n請使用真實攝像頭直播拍攝' };
};

// ── Obstacle + liveness gate ─────────────────────────────────────
const checkObstacleAndLiveness = async (face) => {
    const patch = sampleFacePatch(face, 128);
    const skinRatioLower = computeSkinRatioRegion(patch, 'lower');
    const skinRatioUpper = computeSkinRatioRegion(patch, 'upper');

    if (skinRatioLower < 0.25) {
        alert('❌ 檢測到口罩或下方遮擋物。\n請移除口罩/圍巾以便系統讀取真正的臉部肌膚。');
        return false;
    }
    if (skinRatioUpper < 0.40) {
        alert('❌ 檢測到眼部或上臉遮擋物。\n請撥開頭髮或移除遮擋物再重試。');
        return false;
    }

    // 額頭專屬檢查：在臉的 bounding box 上方採樣，若膚色比例低表示有帽子遮住
    try {
        const foreheadPatch = sampleForeheadPatch(face);
        const skinRatioForehead = computeSkinRatioFlat(foreheadPatch);
        if (skinRatioForehead < 0.20) {
            alert('❌ 偵測到額頭被遮住（帽子／頭帶）。\n請移除後再拍攝，以便正確讀取膚色。');
            return false;
        }
    } catch (e) { /* 無法取得額頭區域時跳過此項檢查 */ }

    const rotationResult = await detectHeadRotation();
    if (!rotationResult.success) return false;

    const coherenceScore = analyzeSkinTextureCoherence(patch);
    if (coherenceScore < 50) {
        updateDebugPanel({ isLive: false, reason: '皮膚紋理不自然，可能是照片或低品質圖像。請使用高質量鏡頭並確保光線充足。' });
        return false;
    }

    updateDebugPanel({ isLive: true, instruction: '活體驗證通過' });
    return true;
};

const awaitUserChallenge = async (face) => {
    try {
        const f = await detectFacesInFrame().catch(() => null);
        if (!f || !f.boundingBox) {
            updateDebugPanel({ isLive: false, reason: '臉部檢測失敗，請確保臉部仍在鏡頭內' });
            return false;
        }
    } catch (e) { return false; }
    return true;
};
