# Spanish (es) — style guide

Yoiful lets small businesses issue branded prepaid cards. The first customers
are cafés, bakeries and shops in **Mexico**, and their staff use the app at the
counter. It talks like a helpful coworker: short, plain and friendly.

Reuse the wording already in `lang/es.json` (Phase 8) wherever the English is
the same, so the copy doesn't drift.

## Register: tú, always

Address the reader as **tú**, never **usted**.

- ✅ `Guarda tus cambios` · `¿Quieres congelar esta tarjeta?` · `Tu perfil`
- ❌ `Guarde sus cambios` · `¿Desea congelar esta tarjeta?` · `Su perfil`

Use **ustedes**, never **vosotros**, for a plural you.

## Mexican-neutral vocabulary, understandable everywhere

Write for Mexico first (`Agregar fondos`, `Cobrar`, `correo`), avoiding
Iberian words (`vale`, `ordenador`, `móvil`, `vosotros`). Prefer `correo`
(or `correo electrónico`) over `email`.

## Sentence case, not Title Case

Capitalise the first word and proper nouns only.

- `Add funds` → `Agregar fondos`
- `Business settings` → `Configuración del negocio`

## Buttons: infinitive

A button takes the infinitive: `Guardar`, `Cobrar`, `Congelar tarjeta`,
`Agregar fondos`. Prose that tells the reader to do something uses the tú
imperative: `Contacta a soporte`.

## Confirmations (toasts)

Past-tense confirmations use the impersonal `Se …` form Phase 8 settled on:
`Card frozen.` → `Se congeló la tarjeta.`, `Plan saved.` → `Se guardó el plan.`
When the sentence is about the reader, use tú: `Switched to {name}.` →
`Cambiaste a {name}.`

## Length

Spanish runs longer than English, and badges, table headings and buttons are
tight. Where two renderings are equally good, take the shorter one.

## Punctuation

Open questions and exclamations: `¿Congelar esta tarjeta?`, `¡Gracias!`.

## Placeholders — the one hard rule

| Syntax   | Where it comes from        | Example              |
| -------- | -------------------------- | -------------------- |
| `{name}` | UI strings (duckalization) | `Se cobró a {code}.` |
| `:name`  | Laravel's strings          | `Se cobró a :code.`  |

Never translate, rename or drop a placeholder; reorder freely. `{count}` in
a plural appears in every form.

## Context

When an entry has a `context`, the same English word means different things
in different places ("Charge" the button vs "Charge" the transaction type;
"Active" a card vs "Active" a business). Entries with the same English and
different contexts are expected to differ.

## Card and business statuses

`card` (tarjeta) and `organization` (organización) are feminine: `Activa`,
`Congelada`, `Agotada`, `Cancelada`, `Suspendida`.
