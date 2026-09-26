import { Camera, CameraOff, RotateCcw } from 'lucide-react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/use-translation';

type Detector = {
    detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]>;
};

type NativeDetectorClass = {
    new (options: { formats: string[] }): Detector;
    getSupportedFormats(): Promise<string[]>;
};

type Status = 'starting' | 'scanning' | 'denied' | 'unsupported' | 'error';

/** How often to look for a QR code in the video, in milliseconds. */
const SCAN_INTERVAL = 120;

/** After a detection, wait this long before reading again. */
const COOLDOWN = 1500;

let detectorPromise: Promise<Detector> | null = null;

/**
 * The browser's own BarcodeDetector when it reads QR codes (Android Chrome),
 * otherwise the barcode-detector ponyfill, which runs ZXing in WebAssembly
 * (iOS Safari, desktop Safari and Firefox). The .wasm file is bundled by
 * Vite and served from this app, not from a CDN.
 */
function loadDetector(): Promise<Detector> {
    detectorPromise ??= (async (): Promise<Detector> => {
        const Native = (
            window as unknown as { BarcodeDetector?: NativeDetectorClass }
        ).BarcodeDetector;

        if (Native) {
            try {
                const formats = await Native.getSupportedFormats();

                if (formats.includes('qr_code')) {
                    return new Native({ formats: ['qr_code'] });
                }
            } catch {
                // Fall through to the ponyfill.
            }
        }

        const [{ BarcodeDetector, prepareZXingModule }, { default: wasmUrl }] =
            await Promise.all([
                import('barcode-detector/ponyfill'),
                import('zxing-wasm/reader/zxing_reader.wasm?url'),
            ]);

        await prepareZXingModule({
            overrides: {
                locateFile: (path: string, prefix: string) =>
                    path.endsWith('.wasm') ? wasmUrl : prefix + path,
            },
            fireImmediately: true,
        });

        return new BarcodeDetector({ formats: ['qr_code'] });
    })();

    detectorPromise.catch(() => {
        detectorPromise = null;
    });

    return detectorPromise;
}

function cameraStatus(error: unknown): Status {
    if (error instanceof DOMException) {
        if (
            error.name === 'NotAllowedError' ||
            error.name === 'SecurityError'
        ) {
            return 'denied';
        }

        if (
            error.name === 'NotFoundError' ||
            error.name === 'OverconstrainedError'
        ) {
            return 'unsupported';
        }
    }

    return 'error';
}

const messages: Record<
    Exclude<Status, 'starting' | 'scanning'>,
    { title: string; description: string }
> = {
    denied: {
        title: 'Camera access is blocked',
        description:
            'Allow camera access for this site in your browser settings, then try again.',
    },
    unsupported: {
        title: 'No camera available',
        description:
            'This device or browser has no camera the reader can use. The reader needs a secure (HTTPS) connection.',
    },
    error: {
        title: 'The camera did not start',
        description: 'Close other apps that use the camera and try again.',
    },
};

type Props = {
    /** Called with the raw text of each QR code read. */
    onDetect: (value: string) => void;
    /** Stop reading codes, for example while a scan is being looked up. */
    paused?: boolean;
    className?: string;
};

/**
 * Live camera view that reads QR codes. It uses the back camera, shows a
 * framing guide, and stops the camera when it unmounts.
 */
export function QrScanner({ onDetect, paused = false, className }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const detectorRef = useRef<Detector | null>(null);
    const [status, setStatus] = useState<Status>('starting');
    const [attempt, setAttempt] = useState(0);
    const { t } = useTranslation();

    const handleDetect = useEffectEvent((value: string) => onDetect(value));

    // Start the camera and the detector together.
    useEffect(() => {
        let cancelled = false;
        let stream: MediaStream | null = null;

        async function start() {
            setStatus('starting');

            if (!navigator.mediaDevices?.getUserMedia) {
                setStatus('unsupported');

                return;
            }

            try {
                // Start loading the detector while the camera asks for
                // permission.
                const detectorReady = loadDetector();
                detectorReady.catch(() => {});

                const media = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                });

                // Unmounted while waiting (or Strict Mode's double effect):
                // release this camera right away.
                if (cancelled) {
                    media.getTracks().forEach((track) => track.stop());

                    return;
                }

                stream = media;

                const video = videoRef.current;

                if (!video) {
                    return;
                }

                video.srcObject = media;
                await video.play();
                detectorRef.current = await detectorReady;

                if (cancelled) {
                    return;
                }

                setStatus('scanning');
            } catch (error) {
                if (!cancelled) {
                    setStatus(cameraStatus(error));
                }
            }
        }

        void start();

        return () => {
            cancelled = true;
            stream?.getTracks().forEach((track) => track.stop());

            if (videoRef.current) {
                videoRef.current.srcObject = null;
            }
        };
    }, [attempt]);

    // Read frames while scanning and not paused.
    useEffect(() => {
        if (paused || status !== 'scanning') {
            return;
        }

        let cancelled = false;
        let timer: number | undefined;

        async function tick() {
            const video = videoRef.current;
            const detector = detectorRef.current;
            let delay = SCAN_INTERVAL;

            if (
                video &&
                detector &&
                video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA
            ) {
                try {
                    const [code] = await detector.detect(video);

                    if (!cancelled && code?.rawValue) {
                        handleDetect(code.rawValue);
                        delay = COOLDOWN;
                    }
                } catch {
                    // A frame that cannot be read; try the next one.
                }
            }

            if (!cancelled) {
                timer = window.setTimeout(() => void tick(), delay);
            }
        }

        void tick();

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [paused, status]);

    const problem =
        status === 'starting' || status === 'scanning'
            ? null
            : messages[status];

    return (
        <div
            className={cn(
                'relative isolate overflow-hidden rounded-xl bg-black',
                className,
            )}
        >
            <video
                ref={videoRef}
                className="size-full object-cover"
                playsInline
                muted
                autoPlay
                aria-label={t('Camera view')}
            />

            {status === 'scanning' && (
                <div
                    className="pointer-events-none absolute inset-0 flex items-center justify-center"
                    aria-hidden="true"
                >
                    <div
                        className={cn(
                            'aspect-square w-3/5 max-w-72 rounded-2xl border-4 border-white/90 shadow-[0_0_0_100vmax_rgb(0_0_0/0.45)] transition-opacity',
                            paused && 'opacity-40',
                        )}
                    />
                </div>
            )}

            {status === 'starting' && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white">
                    <Spinner className="size-8" />
                    <p className="text-sm">{t('Starting camera…')}</p>
                </div>
            )}

            {problem && (
                <div className="absolute inset-0 flex items-center justify-center bg-background p-4">
                    <Alert className="max-w-sm">
                        {status === 'denied' ? <CameraOff /> : <Camera />}
                        <AlertTitle>{t(problem.title)}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3">
                            <p>{t(problem.description)}</p>
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => setAttempt((value) => value + 1)}
                            >
                                <RotateCcw data-icon="inline-start" />
                                {t('Try again')}
                            </Button>
                        </AlertDescription>
                    </Alert>
                </div>
            )}
        </div>
    );
}
