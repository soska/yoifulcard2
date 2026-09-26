import { isReaderSoundOn } from '@/hooks/use-reader-storage';

type Tone = 'scan' | 'success' | 'error';

const tones: Record<Tone, { frequencies: number[]; duration: number }> = {
    scan: { frequencies: [880], duration: 0.08 },
    success: { frequencies: [660, 990], duration: 0.12 },
    error: { frequencies: [220], duration: 0.25 },
};

let context: AudioContext | null = null;

/**
 * A short beep made with Web Audio, so there is no sound file to load.
 * Silent unless the user turned sound on in the reader.
 */
export function playReaderSound(tone: Tone): void {
    if (!isReaderSoundOn() || typeof window.AudioContext !== 'function') {
        return;
    }

    try {
        context ??= new AudioContext();
        void context.resume();

        const { frequencies, duration } = tones[tone];
        let start = context.currentTime;

        for (const frequency of frequencies) {
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.2, start);
            gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + duration);
            start += duration;
        }
    } catch {
        // Audio is feedback only; ignore devices that refuse it.
    }
}

/**
 * iOS only plays Web Audio after a tap. Call this from the tap that turns
 * sound on.
 */
export function unlockReaderSound(): void {
    if (typeof window.AudioContext !== 'function') {
        return;
    }

    try {
        context ??= new AudioContext();
        void context.resume();
    } catch {
        // Ignore: sound stays silent.
    }
}
