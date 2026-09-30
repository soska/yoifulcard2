import { describe, expect, it } from 'vitest';
import { flashMessage } from '@/lib/flash';

// No catalog is booted here, so `__()` returns the English source and
// activeLocale() is `en`.
describe('ledger toasts', () => {
    it('say the amount and the new balance', () => {
        expect(
            flashMessage({
                code: 'ledger.charged',
                params: {
                    code: 'YGFT-CD1A',
                    amount: '85.50',
                    balance: '414.50',
                    currency: 'USD',
                },
            }),
        ).toBe('Charged $85.50 to YGFT-CD1A. Balance: $414.50.');

        expect(
            flashMessage({
                code: 'ledger.loaded',
                params: {
                    code: 'YGFT-CD1A',
                    amount: '100.00',
                    balance: '514.50',
                    currency: 'USD',
                },
            }),
        ).toBe('Added $100.00 to YGFT-CD1A. Balance: $514.50.');
    });

    it('fall back to the code alone when the amounts are missing', () => {
        // A toast flashed before the amounts were sent (a session that
        // outlived a deploy).
        expect(
            flashMessage({
                code: 'ledger.charged',
                params: { code: 'YGFT-CD1A' },
            }),
        ).toBe('Charged YGFT-CD1A.');

        expect(
            flashMessage({
                code: 'ledger.loaded',
                params: { code: 'YGFT-CD1A' },
            }),
        ).toBe('Funds added to YGFT-CD1A.');
    });
});
