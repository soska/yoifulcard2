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
                    code: 'YGFT-CD2A7K',
                    amount: '85.50',
                    balance: '414.50',
                    currency: 'USD',
                },
            }),
        ).toBe('Charged $85.50 to YGFT-CD2A7K. Balance: $414.50.');

        expect(
            flashMessage({
                code: 'ledger.loaded',
                params: {
                    code: 'YGFT-CD2A7K',
                    amount: '100.00',
                    balance: '514.50',
                    currency: 'USD',
                },
            }),
        ).toBe('Added $100.00 to YGFT-CD2A7K. Balance: $514.50.');

        expect(
            flashMessage({
                code: 'ledger.activated',
                params: {
                    code: 'YGFT-CD2A7K',
                    amount: '200.00',
                    balance: '200.00',
                    currency: 'USD',
                },
            }),
        ).toBe('Activated YGFT-CD2A7K with $200.00.');
    });

    it('fall back to the code alone when the amounts are missing', () => {
        // A toast flashed before the amounts were sent (a session that
        // outlived a deploy).
        expect(
            flashMessage({
                code: 'ledger.charged',
                params: { code: 'YGFT-CD2A7K' },
            }),
        ).toBe('Charged YGFT-CD2A7K.');

        expect(
            flashMessage({
                code: 'ledger.loaded',
                params: { code: 'YGFT-CD2A7K' },
            }),
        ).toBe('Funds added to YGFT-CD2A7K.');

        expect(
            flashMessage({
                code: 'ledger.activated',
                params: { code: 'YGFT-CD2A7K' },
            }),
        ).toBe('Card YGFT-CD2A7K activated.');
    });
});

describe('batch toasts', () => {
    it('say how many cards the batch has', () => {
        expect(
            flashMessage({
                code: 'batch.created',
                params: { count: '50', beyond: '0' },
            }),
        ).toBe('Created a batch of 50 cards.');

        expect(
            flashMessage({ code: 'batch.created', params: { count: '1' } }),
        ).toBe('Created a batch of 1 card.');
    });

    it('warn when stock has no room under the card limit', () => {
        expect(
            flashMessage({
                code: 'batch.created',
                params: { count: '50', beyond: '12' },
            }),
        ).toBe(
            'Batch created. 12 cards in stock have no room under your card limit yet.',
        );
    });

    it('say how many cards a void cancelled', () => {
        expect(
            flashMessage({ code: 'batch.voided', params: { count: '3' } }),
        ).toBe('Batch voided. 3 cards cancelled.');
        expect(
            flashMessage({
                code: 'card.voided',
                params: { code: 'YGFT-CD2A7K' },
            }),
        ).toBe('Card YGFT-CD2A7K voided.');
    });
});
