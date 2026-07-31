import assert from 'node:assert/strict';
import test from 'node:test';
import {
  readPluginHeaderVersion,
  readStableTag,
  validateReleaseMetadata,
} from '../../scripts/validate-release-metadata.mjs';

const wpcsHeader = `<?php
/**
 * Plugin Name: Slug-Free Permalinks
 * Version: 1.4.8
 */`;

test('reads a version from a WPCS plugin header', () => {
  assert.equal(readPluginHeaderVersion(wpcsHeader), '1.4.8');
});

test('reads a version from a legacy plain plugin header', () => {
  assert.equal(readPluginHeaderVersion('Version: 1.4.8'), '1.4.8');
});

test('reads the WordPress.org stable tag', () => {
  assert.equal(readStableTag('Stable tag: 1.4.8'), '1.4.8');
});

test('accepts matching release metadata', () => {
  assert.equal(
    validateReleaseMetadata('1.4.8', {
      metadataVersion: '1.4.8',
      pluginContents: wpcsHeader,
      readmeContents: 'Stable tag: 1.4.8',
    }),
    '1.4.8'
  );
});

test('rejects a mismatched plugin header', () => {
  assert.throws(
    () =>
      validateReleaseMetadata('1.4.8', {
        metadataVersion: '1.4.8',
        pluginContents: ' * Version: 1.4.7',
        readmeContents: 'Stable tag: 1.4.8',
      }),
    /Plugin header version \(1\.4\.7\) does not match expected version \(1\.4\.8\)/
  );
});

test('rejects a non-semantic expected version', () => {
  assert.throws(
    () =>
      validateReleaseMetadata('v1.4.8', {
        metadataVersion: '1.4.8',
        pluginContents: wpcsHeader,
        readmeContents: 'Stable tag: 1.4.8',
      }),
    /Expected a semantic version in x\.y\.z form/
  );
});
