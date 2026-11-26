#!/usr/bin/env node
// ExmentPM: the Markdown of the description, the wiki and the comments (ISS00245).
//
//   node project-management/tools/test-pm-markdown.js
//
// The text is escaped first and every tag in the result is one the renderer
// wrote: what is checked here is what each rule makes, and that nothing a
// writer types can become markup or a script.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const SRC = path.join(__dirname, '..', 'ExmentPM', 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');

// A function declared in a source file: from its name to the closing brace at
// its own indent (counting braces is fooled by the ones inside the regexes).
function functionSource(source, name) {
    const start = source.indexOf('function ' + name + '(');
    assert.ok(start >= 0, 'function ' + name + ' is not in the source');
    const lineStart = source.lastIndexOf('\n', start) + 1;
    const indent = source.slice(lineStart, start).replace(/[^ \t]/g, '');
    const one = source.slice(start, source.indexOf('\n', start));
    if (/\}\s*$/.test(one) && (one.match(/\{/g) || []).length === (one.match(/\}/g) || []).length) { return one; }
    const end = source.indexOf('\n' + indent + '}', start);
    assert.ok(end >= 0, 'function ' + name + ' does not end');
    return source.slice(start, end + indent.length + 2);
}

let failed = 0;
function test(name, fn) {
    try {
        fn();
        console.log('ok   ' + name);
    } catch (e) {
        failed++;
        console.log('FAIL ' + name + '\n     ' + String(e && e.message || e).split('\n').join('\n     '));
    }
}

const detail = read('issue-detail.js');
const core = read('core.js');
const errors = read('errors.js');
const code = [
    functionSource(core, 'esc'),
    functionSource(errors, 'reEsc'),
    functionSource(detail, 'unesc'),
    functionSource(detail, 'safeHref'),
    functionSource(detail, 'mdListItem'),
    functionSource(detail, 'mdBlocks'),
    functionSource(detail, 'renderCommentText'),
    // what the page knows: no mentions, no issues, files under /files/
    'function imgSrcOk(u) { return /^\\/files\\//.test(String(u)) || /^https:\\/\\//.test(String(u)); }',
    'function mentions() { return { re: null, by: {} }; }',
    'function issueByKeyNum() { return null; } function issueById() { return null; } function issueByIk() { return null; }',
    'function issueHash(id) { return "issue-" + id; }',
    'var D = { project: { key: "PRJ" } };',
    'return renderCommentText;',
].join('\n');
const md = new Function(code)();

const has = (html, part) => assert.ok(html.indexOf(part) >= 0, 'missing ' + JSON.stringify(part) + ' in\n' + html);
const lacks = (html, part) => assert.ok(html.indexOf(part) < 0, 'unexpected ' + JSON.stringify(part) + ' in\n' + html);

// ---- what was there before -------------------------------------------------
test('headings, bold, italic, strike, code', () => {
    const h = md('# Title\n**bold** *it* ~~gone~~ `x < y`');
    has(h, '<h1>Title</h1>');
    has(h, '<b>bold</b>');
    has(h, '<i>it</i>');
    has(h, '<s>gone</s>');
    has(h, '<code>x &lt; y</code>');
});
test('a table needs its divider row', () => {
    has(md('| a | b |\n|---|---|\n| 1 | 2 |'), '<table><thead><tr><th>a</th><th>b</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>');
    lacks(md('| a | b |\n| 1 | 2 |'), '<table>');
});
test('a task list', () => {
    const h = md('- [ ] todo\n- [x] done');
    has(h, '<li class="task"><input type="checkbox" disabled> todo</li>');
    has(h, '<li class="task"><input type="checkbox" disabled checked> done</li>');
});
test('a list is closed, each item once', () => {
    assert.strictEqual(md('- a\n- b'), '<ul><li>a</li><li>b</li></ul>');
    assert.strictEqual(md('1. a\n2. b'), '<ol><li>a</li><li>b</li></ol>');
});

// ---- what was missing ------------------------------------------------------
test('a list inside an item', () => {
    assert.strictEqual(md('- a\n  - a1\n  - a2\n- b'), '<ul><li>a<ul><li>a1</li><li>a2</li></ul></li><li>b</li></ul>');
    assert.strictEqual(md('1. one\n   - sub\n2. two'), '<ol><li>one<ul><li>sub</li></ul></li><li>two</li></ol>');
    assert.strictEqual(md('- a\n    - deep\n- b'), '<ul><li>a<ul><li>deep</li></ul></li><li>b</li></ul>');
});
test('+ bullets, and a numbered list that starts later', () => {
    assert.strictEqual(md('+ a\n+ b'), '<ul><li>a</li><li>b</li></ul>');
    has(md('3. c\n4. d'), '<ol start="3">');
    has(md('1) a\n2) b'), '<ol><li>a</li>');
});
test('a quote of several lines, and one inside it', () => {
    assert.strictEqual(md('> one\n> two'), '<blockquote>one<br>two</blockquote>');
    assert.strictEqual(md('> out\n> > in\n> back'), '<blockquote>out<blockquote>in</blockquote>back</blockquote>');
});
test('the language of a fence is not a line of its code', () => {
    const h = md('```js\nvar a = 1;\n```');
    has(h, '<pre class="bl-code" data-lang="js">var a = 1;</pre>');
    has(md('```\nplain\n```'), '<pre class="bl-code">plain</pre>');
});
test('the underscore forms, not inside a word', () => {
    has(md('__bold__ and _it_'), '<b>bold</b> and <i>it</i>');
    has(md('***both***'), '<b><i>both</i></b>');
    has(md('snake_case_name and file_name_v2.txt'), 'snake_case_name and file_name_v2.txt');
});
test('a link with a title, and <address>', () => {
    has(md('[site](https://example.com "Example")'), '<a href="https://example.com" target="_blank" rel="noopener" title="Example">site</a>');
    has(md('see <https://example.com/a?b=1&c=2>'), '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener">https://example.com/a?b=1&amp;c=2</a>');
    lacks(md('see <https://example.com/a>'), '&gt;"');
});
test('a bare address ends at a quote or an angle bracket', () => {
    has(md('"https://example.com"'), '<a href="https://example.com" target="_blank" rel="noopener">https://example.com</a>&quot;');
});
test('alignment of the table', () => {
    has(md('| l | c | r |\n|:--|:-:|--:|\n| 1 | 2 | 3 |'), '<td style="text-align:left">1</td><td style="text-align:center">2</td><td style="text-align:right">3</td>');
});
test('a backslash keeps a character as it is', () => {
    has(md('\\*not italic\\*'), '*not italic*');
    has(md('\\# not a heading'), '# not a heading');
    lacks(md('\\# not a heading'), '<h1>');
});

// ---- nothing typed becomes markup ------------------------------------------
test('html is text', () => {
    const h = md('<script>alert(1)</script><img src=x onerror=alert(1)>');
    lacks(h, '<script');
    lacks(h, '<img');
    has(h, '&lt;script&gt;');
});
test('a link to javascript: is not a link', () => {
    lacks(md('[x](javascript:alert(1))'), 'href="javascript');
    lacks(md('<javascript:alert(1)>'), '<a ');
    lacks(md('![x](javascript:alert(1))'), '<img');
});
test('a title cannot close its attribute', () => {
    // the quotes stay escaped inside the attribute: no attribute of its own opens
    const h = md('[x](https://e.com "a" onclick="b")');
    lacks(h, '" onclick');
    has(h, 'title="a&quot; onclick=&quot;b"');
});
test('a fence language cannot carry markup', () => {
    lacks(md('```js"><script>\nx\n```'), '<script');
});
test('every link opens apart from the page', () => {
    const h = md('[a](https://a.example) https://b.example <https://c.example>');
    assert.strictEqual((h.match(/<a /g) || []).length, 3);
    assert.strictEqual((h.match(/rel="noopener"/g) || []).length, 3);
});
test('a picture only from the site\'s files (or https)', () => {
    has(md('![p](/files/abc)'), '<img src="/files/abc"');
    lacks(md('![p](/admin/logout)'), '<img');
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall ok');
