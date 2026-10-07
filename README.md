Yoiful (Laravel rewrite). Spec: [../yoifulcard/REWRITE-PRD.md](../yoifulcard/REWRITE-PRD.md). Build plan: [../yoifulcard/REWRITE-PLAN.md](../yoifulcard/REWRITE-PLAN.md).

## Demo data

Local only (the seeder refuses to run in production). Safe to run more than once; it never wipes the database.

```bash
php artisan db:seed --class=DemoSeeder
```

Every login uses the password `yoiful-test-123`:

| Login                   | Access                                                                 |
| ----------------------- | ---------------------------------------------------------------------- |
| `armando@yoiful.test`   | Superadmin, owner of Café La Esquina, manager at Panadería Dulce Hogar |
| `owner2@yoiful.test`    | Owner of Panadería Dulce Hogar (card usage above 80%)                  |
| `employee@yoiful.test`  | Employee at Panadería Dulce Hogar                                      |
| `suspended@yoiful.test` | Owner of Bicis del Norte (suspended)                                   |

The seeder prints a few public card URLs (`/c/{token}`) when it finishes.

## Printing card batches

Batch PDFs are made on the queue and expired ones are pruned by the scheduler
(`model:prune`, hourly). In development, `pnpm dev` starts both queue workers
along with the server and Vite. Elsewhere, run the workers and the scheduler
alongside the app:

```bash
php artisan queue:work
php artisan queue:work pdfs
php artisan schedule:work
```

A PDF can take up to 10 minutes to render, so on the database queue it goes
to its own `pdfs` connection, whose `retry_after` (`DB_PDF_QUEUE_RETRY_AFTER`,
660 seconds) is longer than that. Set `PDF_QUEUE_CONNECTION` to use another
connection.
