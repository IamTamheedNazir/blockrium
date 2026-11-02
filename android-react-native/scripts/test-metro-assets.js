const assert = require('assert');
const path = require('path');
const {getAssetData} = require('metro/src/Assets');

const relativeAssetPath = 'assets/images/backgroundImage.png';
const absoluteAssetPath = path.resolve(relativeAssetPath);

getAssetData(absoluteAssetPath, relativeAssetPath, [], null, '/assets')
  .then(asset => {
    assert(asset.width > 0, 'Metro should detect the PNG width');
    assert(asset.height > 0, 'Metro should detect the PNG height');
    assert.strictEqual(asset.type, 'png');
    process.stdout.write(`Metro asset dimensions: ${asset.width}x${asset.height}\n`);
  })
  .catch(error => {
    console.error(error);
    process.exitCode = 1;
  });
