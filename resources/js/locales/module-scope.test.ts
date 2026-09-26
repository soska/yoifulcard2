import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import ts from 'typescript';
import { expect, it } from 'vitest';

/*
 * `__()` at module scope runs at import time, before bootLocale() has loaded
 * the catalog, and freezes English into the bundle. It compiles, passes in
 * English, and silently breaks Spanish, so it is checked here: every `__()`
 * call must sit inside a function (a component, a hook, a label function, a
 * page's layout function).
 */

const root = join(process.cwd(), 'resources/js');
const skipped = ['actions', 'routes', 'wayfinder', 'locales', 'components/ui'];

function sourceFiles(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (skipped.includes(relative(root, path))) {
            return [];
        }

        if (entry.isDirectory()) {
            return sourceFiles(path);
        }

        return /\.tsx?$/.test(entry.name) &&
            !/\.(d|test)\.tsx?$/.test(entry.name)
            ? [path]
            : [];
    });
}

/** `name:line` of every `__()` call outside any function. */
function moduleScopeCalls(name: string, text: string): string[] {
    const file = ts.createSourceFile(name, text, ts.ScriptTarget.Latest, true);
    const found: string[] = [];

    const visit = (node: ts.Node, insideFunction: boolean): void => {
        if (
            !insideFunction &&
            ts.isCallExpression(node) &&
            ts.isIdentifier(node.expression) &&
            node.expression.text === '__'
        ) {
            const { line } = file.getLineAndCharacterOfPosition(
                node.getStart(),
            );
            found.push(`${name}:${line + 1}`);
        }

        const inside = insideFunction || ts.isFunctionLike(node);
        ts.forEachChild(node, (child) => visit(child, inside));
    };

    visit(file, false);

    return found;
}

it('finds the source files', () => {
    expect(sourceFiles(root).length).toBeGreaterThan(50);
});

it('has no __() call at module scope', () => {
    const calls = sourceFiles(root).flatMap((path) =>
        moduleScopeCalls(relative(root, path), readFileSync(path, 'utf8')),
    );

    expect(calls).toEqual([]);
});

it('catches a module-scope __() call', () => {
    // Guards the guard: a top-level table is caught, a function is not.
    const probe = [
        "const LABELS = { owner: __('Owner') };",
        "const label = () => __('Owner');",
        "function other() { return [__('Owner')]; }",
    ].join('\n');

    expect(moduleScopeCalls('probe.ts', probe)).toEqual(['probe.ts:1']);
});
