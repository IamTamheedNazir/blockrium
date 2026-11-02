import {readFileSync} from 'node:fs';
import {join} from 'node:path';

describe('Community bottom-tab layout', () => {
  it('reserves enough scroll space for the floating bottom navigation', () => {
    const source = readFileSync(join(__dirname, '../source/organisms/HomeOrg/Community.tsx'), 'utf8');
    const contentStyle = source.match(/content:\s*\{[^}]*paddingBottom:\s*(\d+)/);

    expect(contentStyle).not.toBeNull();
    expect(Number(contentStyle?.[1])).toBeGreaterThanOrEqual(120);
  });
});
