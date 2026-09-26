import { Volume2, VolumeX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useReaderSound } from '@/hooks/use-reader-storage';
import { playReaderSound, unlockReaderSound } from '@/lib/reader-sound';
import { useTranslation } from '@/hooks/use-translation';

/** Turns reader beeps on or off for this device. Off by default. */
export function SoundToggle() {
    const { t } = useTranslation();
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
            {enabled ? t('Sound on') : t('Sound off')}
        </Button>
    );
}
