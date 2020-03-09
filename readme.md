# Resumable.js used as an ES6 module

Based on https://github.com/23/resumable.js

## How it was built

* import of the library with `npm i resumablejs`
* "modularization" of the library by copying it _verbatim_ and adding an export :
```
# That's what does the `modulize:resumable` task in the `package.json` of this project
cp node_modules/resumablejs/resumable.js ./js/resumable-es6.js
echo 'export default Resumable' >> ./js/resumable-es6.js
```
* writing of the `ResumableWidget` based upon the initial demo in the `resumable.js` library

## How to use

On a computer with `GIT`, `PHP` and `NPM` installed :
```
git clone https://github.com/murrutia/resumablejs-es6.git

cd resumablejs-es6

npm install

npm run start

open http://localhost:8000
```
