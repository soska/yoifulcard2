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
| `cometa@yoiful.test`    | Owner of Café Cometa (rewards: points per amount spent)                |
| `yoga@yoiful.test`      | Owner of Loto Yoga Studio (rewards: a point per visit)                 |

`armando@yoiful.test` also manages Café Cometa and Loto Yoga Studio, and
`employee@yoiful.test` is an employee at Café Cometa too, so the reader's
earn and redeem tabs can be tried as staff.

The two rewards businesses show the points features:

- **Café Cometa** earns one point per 10 pesos, and its points expire after a
  month idle. It has a card with prepaid balance and points, a rewards-only
  card for a customer who pays cash, a card that already went through an
  expiry, and one idle card whose 40 points are due to expire: run
  `php artisan rewards:expire-points` to watch it happen.
- **Loto Yoga Studio** earns a point per visit, and its points expire after
  three months. It has a class pack used up (depleted) with ten points ready
  to redeem, a regular student who has already redeemed once, and a card one
  visit away from a free class.

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

## Reward points

Points expire when a business sets an expiry (in months of no activity on the
card). The scheduler runs `php artisan rewards:expire-points` daily; it writes
an `expire` entry for each idle card and is safe to run again. In development
it runs with `php artisan schedule:work`, or by hand:

```bash
php artisan rewards:expire-points
```
