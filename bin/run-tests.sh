#!/usr/bin/env sh

set -eu

repository_root=$(CDPATH= cd -P "$(dirname "$0")/.." && pwd)

printf '%s\n' 'Deprecated: bin/run-tests.sh is deprecated; use: make test-all CONFIRM=testdb' >&2

exec make -C "$repository_root" test-all "$@"
