'use strict';

// Shepherd 12 bundles its old merge helper, so an npm override alone does not
// patch the shipped browser code. Delegate its sole merge entry point to the
// patched dependency; fail the build if the pinned distribution changes shape.
module.exports = function shepherdSafeMerge(source) {
  const legacy = 'return deepmergeCustom({})(...objects);';
  if (source.split(legacy).length !== 2) {
    throw new Error('Review Shepherd merge remediation against the updated distribution.');
  }
  return "import { deepmerge as patchedDeepmerge } from 'deepmerge-ts';\n" +
    source.replace(legacy, 'return patchedDeepmerge(...objects);');
};
