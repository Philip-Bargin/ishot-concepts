// node --test tests/blocks/export-icons.test.mjs
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { generate, target, ICONS } from "../../scripts/export-icons.mjs";

test("regenerating the icon data gives the committed file byte for byte", () => {
  assert.equal(generate(), readFileSync(target, "utf8"));
});

test("covers every icon the prototype imports and every booking service icon", () => {
  const need = ["arrow-right","briefcase","calendar-plus","camera","chevron-left","chevron-right","circle","circle-alert","circle-check","circle-x","clock","heart","info","link-2","mail","menu","pause","play","sparkles","sun","video","x","image","users","film","star"];
  for (const n of need) assert.ok(n in ICONS, n);
});
