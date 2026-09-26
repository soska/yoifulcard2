import { Volume2, VolumeX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useReaderSound } from '@/hooks/use-reader-storage';
import { playReaderSound, unlockReaderSound } from '@/lib/reader-sound';
import { __ } from '@/i18n';

/** Turns reader beeps on or off for this device. Off by default. */
export function SoundToggle() {
    const { enabled, setEnabled } = useReaderSound();

    return (
        <Button
            variant="outline"
            size="lg"
            className="h-12"
            aria-pressed={enabled}
            onClick={() => {
                if (!enabled) {
                    unlockReaderSound();
                }

                setEnabled(!enabled);

                if (!enabled) {
                    playReaderSound('scan');
                }
            }}
        >
            {enabled ? (
                <Volume2 data-icon="inline-start" />
            ) : (
                <VolumeX data-icon="inline-start" />
            )}
            {enabled ? __('Sound on') : __('Sound off')}
        </Button>
    );
}
