#!/usr/bin/env bash
set -euo pipefail

# Parse arguments
FORCE=false
AUTO_RECONCILE_THRESHOLD=10  # Max file differences to auto-reconcile

while [[ $# -gt 0 ]]; do
  case $1 in
    --force|-f)
      FORCE=true
      shift
      ;;
    --reconcile-threshold)
      AUTO_RECONCILE_THRESHOLD="$2"
      shift 2
      ;;
    --reconcile-threshold=*)
      AUTO_RECONCILE_THRESHOLD="${1#*=}"
      shift
      ;;
    *)
      echo "Unknown option: $1" >&2
      echo "Usage: $0 [--force] [--reconcile-threshold=N]" >&2
      echo "" >&2
      echo "Options:" >&2
      echo "  --force, -f              Overwrite client changes without prompting" >&2
      echo "  --reconcile-threshold=N  Max file differences to auto-reconcile (default: 10)" >&2
      exit 1
      ;;
  esac
done

SOURCE_REPO="$(git rev-parse --show-toplevel)"
BRANCH_NAME="$(git rev-parse --abbrev-ref HEAD)"
CLIENT_REPO="${CLIENT_REPO:-/Users/yoren/jtzl-wp/swipecomic}"

if [[ "$BRANCH_NAME" == "HEAD" ]]; then
  echo "Detached HEAD; please checkout a branch before syncing." >&2
  exit 1
fi

if [[ ! -d "$CLIENT_REPO/.git" ]]; then
  echo "Client repo not found at $CLIENT_REPO" >&2
  echo "Initialize it first with: git init $CLIENT_REPO" >&2
  exit 1
fi

# Track whether the client repo has any commits yet
CLIENT_IS_EMPTY=false
if ! git -C "$CLIENT_REPO" rev-parse HEAD &>/dev/null; then
  CLIENT_IS_EMPTY=true
fi

if [[ "$CLIENT_IS_EMPTY" == "false" ]]; then
  if ! git -C "$CLIENT_REPO" diff --quiet || ! git -C "$CLIENT_REPO" diff --cached --quiet; then
    echo "Client repo has uncommitted changes; commit or stash before syncing." >&2
    exit 1
  fi
fi

STATE_DIR="$(git rev-parse --git-dir)/client-sync"
mkdir -p "$STATE_DIR"
STATE_FILE="$STATE_DIR/${BRANCH_NAME//\//_}.last"

# Define excluded paths — everything NOT deployed to WordPress.org
# The WordPress.org distribution includes only:
#   jtzl-swipecomic.php, readme.txt, uninstall.php, includes/, templates/,
#   admin/ (source CSS/JS), src/, scripts/ (build helpers),
#   composer.json, package.json, package-lock.json,
#   tsconfig.json, esbuild.config.js, postcss.config.js
EXCLUDE_PATHS=(
  # IDE / editor
  ".idea"
  ".vscode"
  ".cursor"
  ".kiro"
  ".claude"
  ".qodo"
  ".playwright-mcp"

  # Git / CI
  ".gitignore"
  ".github"
  ".husky"

  # WordPress.org assets (deployed separately via 10up action)
  ".wordpress-org"

  # Dev config — linting, testing, analysis
  ".eslintignore"
  ".eslintrc.js"
  ".prettierignore"
  ".prettierrc.js"
  "jest.config.js"
  "phpcs.xml.dist"
  "phpunit.xml.dist"

  # Dev / test directories
  "bin"
  "tests"
  ".cache"

  # Documentation (not in WP.org dist)
  "README.md"
  "CLAUDE.md"
  "YOREN.md"
  "AGENTS.md"
  "docs"

  # Generated / build artifacts (gitignored, but exclude as safety)
  "node_modules"
  "vendor"
  "build"
  "dist"
  "coverage"
  ".phpunit.result.cache"
  "var"
)

# Compare source/client content after removing excluded paths.
# Returns 0 when content is equivalent, 1 otherwise.
compare_filtered_trees() {
  local source_ref="${1:-HEAD}"
  local client_ref="${2:-$BRANCH_NAME}"
  local show_diff="${3:-false}"
  local temp_source temp_client

  temp_source="$(mktemp -d)"
  temp_client="$(mktemp -d)"

  git -C "$SOURCE_REPO" archive "$source_ref" | tar -x -C "$temp_source"
  git -C "$CLIENT_REPO" archive "$client_ref" | tar -x -C "$temp_client"

  for path in "${EXCLUDE_PATHS[@]}"; do
    rm -rf "${temp_source:?}/$path" 2>/dev/null || true
    rm -rf "${temp_client:?}/$path" 2>/dev/null || true
  done

  if diff -rq "$temp_source" "$temp_client" &>/dev/null; then
    rm -rf "$temp_source" "$temp_client"
    return 0
  fi

  if [[ "$show_diff" == "true" ]]; then
    diff -rq "$temp_source" "$temp_client" 2>/dev/null | head -20 || true
  fi

  rm -rf "$temp_source" "$temp_client"
  return 1
}

# Build git pathspec exclusions for use with git diff
PATHSPEC_EXCLUDES=()
for path in "${EXCLUDE_PATHS[@]}"; do
  PATHSPEC_EXCLUDES+=(":(exclude)$path")
done

LAST_SYNCED=""
if [[ -f "$STATE_FILE" ]]; then
  LAST_SYNCED="$(cat "$STATE_FILE")"
fi

# Validate that LAST_SYNCED is still an ancestor of the current branch
BASE_BRANCH="${BASE_BRANCH:-main}"

if [[ -n "$LAST_SYNCED" ]]; then
  if ! git merge-base --is-ancestor "$LAST_SYNCED" "$BRANCH_NAME" 2>/dev/null; then
    echo "Warning: Last synced commit $LAST_SYNCED is no longer an ancestor of $BRANCH_NAME." >&2
    echo "This usually means the source branch history was rewritten (rebase, amend, etc.)." >&2
    echo "" >&2
    echo "To force re-sync from scratch, run:" >&2
    echo "  rm '$STATE_FILE'" >&2
    exit 1
  fi
  COMMITS="$(git rev-list --no-merges --reverse "$LAST_SYNCED..$BRANCH_NAME")"
else
  if [[ "$BRANCH_NAME" == "$BASE_BRANCH" ]]; then
    COMMITS="$(git rev-list --no-merges --reverse "$BRANCH_NAME")"
  else
    MERGE_BASE="$(git merge-base "$BASE_BRANCH" "$BRANCH_NAME" 2>/dev/null || true)"
    if [[ -n "$MERGE_BASE" ]]; then
      COMMITS="$(git rev-list --no-merges --reverse "$MERGE_BASE..$BRANCH_NAME")"
    else
      COMMITS="$(git rev-list --no-merges --reverse "$BRANCH_NAME")"
    fi
  fi
fi

# Check if client repo has commits that weren't created by this sync script
if [[ "$CLIENT_IS_EMPTY" == "false" ]] && [[ -n "$LAST_SYNCED" ]] && git -C "$CLIENT_REPO" rev-parse --verify "$BRANCH_NAME" &>/dev/null; then
  CLIENT_HEAD="$(git -C "$CLIENT_REPO" rev-parse "$BRANCH_NAME" 2>/dev/null || true)"

  if [[ -n "$CLIENT_HEAD" ]]; then
    CLIENT_COMMIT_COUNT="$(git -C "$CLIENT_REPO" rev-list --count "$BRANCH_NAME" 2>/dev/null || echo 0)"

    EXPECTED_COUNT_FILE="$STATE_DIR/${BRANCH_NAME//\//_}.count"
    EXPECTED_COUNT=0
    if [[ -f "$EXPECTED_COUNT_FILE" ]]; then
      EXPECTED_COUNT="$(cat "$EXPECTED_COUNT_FILE")"
    fi

    if [[ "$CLIENT_COMMIT_COUNT" -gt "$EXPECTED_COUNT" ]] && [[ "$EXPECTED_COUNT" -gt 0 ]]; then
      EXTRA_COMMIT_COUNT=$((CLIENT_COMMIT_COUNT - EXPECTED_COUNT))

      CHANGED_FILES="$(git -C "$CLIENT_REPO" diff --name-only HEAD~${EXTRA_COMMIT_COUNT} HEAD 2>/dev/null || true)"

      # Check if all changed files are in excluded paths
      ALL_EXCLUDED=true
      if [[ -n "$CHANGED_FILES" ]]; then
        while IFS= read -r file; do
          FILE_EXCLUDED=false
          for excluded in "${EXCLUDE_PATHS[@]}"; do
            if [[ "$file" == "$excluded" ]] || [[ "$file" == "$excluded"/* ]]; then
              FILE_EXCLUDED=true
              break
            fi
          done
          if [[ "$FILE_EXCLUDED" == "false" ]]; then
            ALL_EXCLUDED=false
            break
          fi
        done <<< "$CHANGED_FILES"
      fi

      if [[ "$ALL_EXCLUDED" == "true" ]]; then
        echo "Client repo has $EXTRA_COMMIT_COUNT extra commit(s), but they only touch excluded paths." >&2
        echo "Updating sync state to acknowledge these commits..." >&2
        echo "$CLIENT_COMMIT_COUNT" > "$EXPECTED_COUNT_FILE"

        if [[ -z "$COMMITS" ]]; then
          echo "No new commits to sync from source. Sync state updated."
          exit 0
        fi
      elif compare_filtered_trees "HEAD" "$BRANCH_NAME"; then
        echo "Client repo has $EXTRA_COMMIT_COUNT extra commit(s), but filtered content matches source." >&2
        echo "Treating repos as synced and updating sync state..." >&2
        git rev-parse HEAD > "$STATE_FILE"
        echo "$CLIENT_COMMIT_COUNT" > "$EXPECTED_COUNT_FILE"
        echo "No new commits to sync for $BRANCH_NAME."
        exit 0
      elif [[ "$FORCE" == "false" ]]; then
        echo "Warning: Client repo has $EXTRA_COMMIT_COUNT commit(s) that weren't synced from source." >&2
        echo "" >&2
        echo "Options:" >&2
        echo "  1. Use --force to overwrite client changes" >&2
        echo "  2. Manually review and merge client changes back to source first" >&2
        echo "" >&2
        echo "Client commits not from sync:" >&2
        git -C "$CLIENT_REPO" log --oneline -n "$EXTRA_COMMIT_COUNT" "$BRANCH_NAME" >&2
        exit 1
      else
        echo "Warning: Overwriting $EXTRA_COMMIT_COUNT client-only commit(s) due to --force flag." >&2
        echo "$CLIENT_COMMIT_COUNT" > "$EXPECTED_COUNT_FILE"
      fi
    fi
  fi
fi

# If no state file exists but client branch exists, check if files are already in sync
if [[ "$CLIENT_IS_EMPTY" == "false" ]] && [[ -z "$LAST_SYNCED" ]] && git -C "$CLIENT_REPO" rev-parse --verify "$BRANCH_NAME" &>/dev/null; then
  echo "No sync state found for $BRANCH_NAME. Checking if repos are already in sync..."

  if compare_filtered_trees "HEAD" "$BRANCH_NAME"; then
    echo "Repos are already in sync! Marking current HEAD as synced."

    git rev-parse HEAD > "$STATE_FILE"
    CLIENT_COMMIT_COUNT="$(git -C "$CLIENT_REPO" rev-list --count "$BRANCH_NAME" 2>/dev/null || echo 0)"
    EXPECTED_COUNT_FILE="$STATE_DIR/${BRANCH_NAME//\//_}.count"
    echo "$CLIENT_COMMIT_COUNT" > "$EXPECTED_COUNT_FILE"

    echo "Sync state initialized for $BRANCH_NAME."
    exit 0
  else
    echo "Repos have different content. Will need to sync."
    echo ""
    echo "Differences found:"
    compare_filtered_trees "HEAD" "$BRANCH_NAME" "true" || true
    echo ""

    echo "WARNING: About to replay $(echo "$COMMITS" | wc -w | tr -d ' ') commits as diffs."
    echo ""
    if [[ "$FORCE" == "false" ]]; then
      echo "If the repos should be in sync, manually fix the differences first."
      echo "Or use --force to proceed anyway."
      exit 1
    fi
    echo "Proceeding due to --force flag..."
  fi
fi

if [[ -z "$COMMITS" ]]; then
  # Before declaring "nothing to sync", verify the branch exists in the client repo
  if ! git -C "$CLIENT_REPO" rev-parse --verify "$BRANCH_NAME" &>/dev/null; then
    echo "Branch $BRANCH_NAME does not exist in client repo, but sync state says we're up to date." >&2
    echo "Clearing stale sync state and re-syncing..." >&2
    rm -f "$STATE_FILE"
    EXPECTED_COUNT_FILE="$STATE_DIR/${BRANCH_NAME//\//_}.count"
    rm -f "$EXPECTED_COUNT_FILE"

    # Recompute commits from merge base
    LAST_SYNCED=""
    if [[ "$BRANCH_NAME" == "$BASE_BRANCH" ]]; then
      COMMITS="$(git rev-list --no-merges --reverse "$BRANCH_NAME")"
    else
      MERGE_BASE="$(git merge-base "$BASE_BRANCH" "$BRANCH_NAME" 2>/dev/null || true)"
      if [[ -n "$MERGE_BASE" ]]; then
        COMMITS="$(git rev-list --no-merges --reverse "$MERGE_BASE..$BRANCH_NAME")"
      else
        COMMITS="$(git rev-list --no-merges --reverse "$BRANCH_NAME")"
      fi
    fi

    if [[ -z "$COMMITS" ]]; then
      echo "No commits to sync (branch has no changes vs $BASE_BRANCH)."
      exit 0
    fi
  else
    echo "No new commits to sync for $BRANCH_NAME."
    exit 0
  fi
fi

# --- Main sync loop: apply diffs instead of wiping and rebuilding ---

EMPTY_TREE="$(git hash-object -t tree /dev/null)"

# For empty repos, the branch will be created by the first commit.
# For existing repos, ensure we're on the right branch.
if [[ "$CLIENT_IS_EMPTY" == "false" ]]; then
  git -C "$CLIENT_REPO" checkout -B "$BRANCH_NAME"
fi

COMMIT_COUNT=0
SKIP_COUNT=0

for commit in $COMMITS; do
  # Get the parent commit (or empty tree for root commits)
  PARENT="$(git rev-parse --verify --quiet "$commit^" 2>/dev/null || echo "$EMPTY_TREE")"

  # Generate a filtered diff to a temp file (avoids binary corruption via shell variables)
  PATCH_FILE="$(mktemp)"
  git diff --binary "$PARENT" "$commit" -- . "${PATHSPEC_EXCLUDES[@]}" > "$PATCH_FILE" || true

  if [[ ! -s "$PATCH_FILE" ]]; then
    # This commit only touched excluded files — nothing to sync
    rm -f "$PATCH_FILE"
    echo "$commit" > "$STATE_FILE"
    SKIP_COUNT=$((SKIP_COUNT + 1))
    continue
  fi

  # Apply the patch to the client repo
  if ! git -C "$CLIENT_REPO" apply --allow-empty "$PATCH_FILE" 2>/dev/null; then
    echo "Warning: patch for $commit did not apply cleanly. Attempting with 3-way merge..." >&2

    if ! git -C "$CLIENT_REPO" apply --3way "$PATCH_FILE" 2>/dev/null; then
      rm -f "$PATCH_FILE"
      echo "Error: Could not apply commit $commit to client repo." >&2
      echo "The client repo may have diverged. Please reconcile manually." >&2
      echo "" >&2
      echo "Source commit: $(git log --oneline -1 "$commit")" >&2
      echo "Last successfully synced: $(cat "$STATE_FILE" 2>/dev/null || echo 'none')" >&2
      exit 1
    fi
  fi
  rm -f "$PATCH_FILE"

  git -C "$CLIENT_REPO" add -A

  if git -C "$CLIENT_REPO" diff --cached --quiet; then
    echo "$commit" > "$STATE_FILE"
    SKIP_COUNT=$((SKIP_COUNT + 1))
    continue
  fi

  # Generate commit message from the diff (truncate without SIGPIPE)
  DIFF_TRUNCATED="$(git -C "$CLIENT_REPO" diff --cached | head -n 5000 || true)"

  MESSAGE="$(echo "$DIFF_TRUNCATED" | llm -m gpt-4o-mini -s "Write a concise git commit message for this diff. Use conventional commits format (e.g., feat:, fix:, refactor:). Only output the subject line, no body or explanation.")"

  if [[ -z "$MESSAGE" ]]; then
    MESSAGE="chore: sync changes"
  fi

  # Preserve original author/committer metadata (including date/time)
  AUTHOR_NAME="$(git -C "$SOURCE_REPO" log -1 --format=%an "$commit")"
  AUTHOR_EMAIL="$(git -C "$SOURCE_REPO" log -1 --format=%ae "$commit")"
  AUTHOR_DATE="$(git -C "$SOURCE_REPO" log -1 --format=%aI "$commit")"
  COMMITTER_NAME="$(git -C "$SOURCE_REPO" log -1 --format=%cn "$commit")"
  COMMITTER_EMAIL="$(git -C "$SOURCE_REPO" log -1 --format=%ce "$commit")"
  COMMITTER_DATE="$(git -C "$SOURCE_REPO" log -1 --format=%cI "$commit")"

  GIT_AUTHOR_NAME="$AUTHOR_NAME" \
  GIT_AUTHOR_EMAIL="$AUTHOR_EMAIL" \
  GIT_AUTHOR_DATE="$AUTHOR_DATE" \
  GIT_COMMITTER_NAME="$COMMITTER_NAME" \
  GIT_COMMITTER_EMAIL="$COMMITTER_EMAIL" \
  GIT_COMMITTER_DATE="$COMMITTER_DATE" \
    git -C "$CLIENT_REPO" commit --no-gpg-sign -m "$MESSAGE"

  echo "$commit" > "$STATE_FILE"
  COMMIT_COUNT=$((COMMIT_COUNT + 1))
done

# Update the expected commit count for future runs
CLIENT_COMMIT_COUNT="$(git -C "$CLIENT_REPO" rev-list --count "$BRANCH_NAME" 2>/dev/null || echo 0)"
EXPECTED_COUNT_FILE="$STATE_DIR/${BRANCH_NAME//\//_}.count"
echo "$CLIENT_COMMIT_COUNT" > "$EXPECTED_COUNT_FILE"

echo "Sync complete for $BRANCH_NAME. Applied $COMMIT_COUNT commit(s), skipped $SKIP_COUNT."
