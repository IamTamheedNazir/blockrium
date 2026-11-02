import {readFileSync} from 'node:fs';
import {join} from 'node:path';

describe('Squad profile layout', () => {
  const source = readFileSync(join(__dirname, '../source/pages/Squads.tsx'), 'utf8');

  it('keeps the three profile metrics in equal-width, separated tiles', () => {
    expect(source).toMatch(/profileStats:\s*\{[^}]*flexDirection:\s*'row'[^}]*gap:\s*8/);
    expect(source).toMatch(/profileStat:\s*\{[^}]*flex:\s*1[^}]*minWidth:\s*0[^}]*borderRadius:/);
    expect(source.match(/style=\{styles\.profileStat\}/g)).toHaveLength(3);
  });

  it('allows long squad names and membership tiers to wrap without squeezing', () => {
    expect(source).toMatch(/squadNameRow:\s*\{[^}]*flexWrap:\s*'wrap'/);
    expect(source).toMatch(/memberTierBadge:\s*\{[^}]*flexShrink:\s*0/);
  });
});
