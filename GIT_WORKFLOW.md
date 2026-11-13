# Git workflow

Two branches live on GitHub:

| Branch | What it is for |
|---|---|
| `master` | Day-to-day work. Commit here. |
| `main` | The stable branch. GitHub's default branch, the one you deploy, and the one CI tests on every push (`.github/workflows/tests.yml`). |

Work flows one way: **commit on `master` → merge into `main` → push `main`**.

## 1. Commit your work on `master`

```bash
git checkout master
git pull origin master          # get what others pushed first

# ...make your changes...

# Run the same checks CI runs, so main never receives a broken commit
php artisan test --compact
vendor/bin/pint --test
npx vp check
npx vp build

git status                      # check what you are about to commit (never .env)
git add -A
git commit -m "Short summary of the change"
git push origin master
```

## 2. Switch to `main`

```bash
git checkout main
git pull origin main
```

Your working tree must be clean (everything committed) or Git refuses to switch.

## 3. Merge `master` into `main`

```bash
git merge master
php artisan test --compact      # one last check on the merged result
git push origin main            # CI runs on this push
```

If `main` had no commits of its own, this is a fast-forward: `main` simply moves up to `master`, no merge commit.

## 4. Bring `main` back into `master`

Only needed when something was committed straight to `main` (for example a hotfix), so `master` does not fall behind:

```bash
git checkout master
git merge main
git push origin master
```

When both branches point to the same commit, they are in sync. Check with:

```bash
git log --oneline -1 main master
```

## If a merge has conflicts

1. `git status` lists the conflicted files.
2. Open each one, keep the right lines, and delete the `<<<<<<<`, `=======` and `>>>>>>>` markers.
3. `git add <file>` for each fixed file, then `git commit` to finish the merge.
4. To give up instead: `git merge --abort` puts everything back as it was.

## After pulling new code

New commits can bring new packages or database changes. Run what applies:

```bash
composer install
npm install
php artisan migrate
```

## Never

- Commit `.env`, `vendor/`, `node_modules/` or uploaded files (they are in `.gitignore`).
- `git push --force` to `main` or `master`. It erases other people's commits on GitHub.
- Run `php artisan key:generate` or `db:seed` on the live server after the first deploy (see `DEPLOY.md`).
