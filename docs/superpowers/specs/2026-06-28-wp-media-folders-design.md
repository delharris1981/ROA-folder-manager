# WordPress Media Folders Plugin — Design Spec

**Date:** 2026-06-28
**Status:** Approved

---

## Overview

A WordPress plugin (WP 6+) that adds real, on-disk folder management to the Media Library. Users can create a nested folder tree under `wp-content/uploads/`, upload files directly into a chosen folder, and move existing files between folders. All operations work on the actual filesystem — no virtual/taxonomy-based folders.

---

## Requirements

- Nestable folders under `wp-content/uploads/` (real directories on disk)
- Folder tree displayed in a left panel inside the Media Library (`upload.php`) and the media modal
- Upload into selected folder: files land at `uploads/my-folder/image.jpg` (no year/month subfolders inside plugin-managed folders)
- Move existing attachments between folders: moves file + all generated thumbnail sizes on disk, updates all WP attachment meta and post content URLs
- Delete folder: user dialog offering move-contents-to-parent or delete-contents, with a confirmation step
- Minimum WordPress version: 6.0
- Built assets committed to repo — end users install the plugin zip, no Node.js required

---

## Architecture

### PHP — OOP with Namespacing

All classes use the `MediaFolders\` namespace. `media-folders.php` only defines constants and calls `( new Plugin() )->init()`.

```
media-folders/
├── media-folders.php              # Bootstrap: constants + instantiate Plugin
├── includes/
│   ├── class-plugin.php           # Orchestrator — registers all hooks
│   ├── class-rest-api.php         # REST endpoint registration + handlers
│   ├── class-folder-manager.php   # Filesystem ops: create, rename, delete, list
│   ├── class-attachment-mover.php # Move files + thumbnails, update DB + post URLs
│   └── class-admin.php            # Enqueue scripts, inject into Media Library
├── src/                           # React source (development only)
├── build/                         # Compiled assets (committed to repo)
└── package.json
```

**Constants** (`media-folders.php`):
- `MEDIA_FOLDERS_PATH` — absolute path to plugin directory
- `MEDIA_FOLDERS_URL` — URL to plugin directory
- `MEDIA_FOLDERS_VERSION` — plugin version string

No static state. Dependencies injected via constructor where needed.

### Build Toolchain

`@wordpress/scripts` — WP's official zero-config webpack wrapper. One `npm run build` before each release; output committed to `build/`. No custom webpack config required.

---

## REST API

Base: `/wp-json/media-folders/v1/`

All endpoints require `current_user_can('upload_files')` via `permission_callback`.

| Method | Endpoint | Params | Purpose |
|--------|----------|--------|---------|
| `GET` | `/folders` | — | Full folder tree (filesystem scan of `uploads/`) |
| `POST` | `/folders` | `path`, `parent_path` | Create a new folder |
| `PATCH` | `/folders` | `path`, `new_name` | Rename a folder |
| `DELETE` | `/folders` | `path`, `action` (`move`\|`delete`), `destination_path` | Delete a folder |
| `POST` | `/attachments/{id}/move` | `destination_path` | Move an attachment to a folder |

**Folder tree:** Built by scanning the filesystem at request time — no extra DB table. The folder structure is the filesystem; WordPress's `_wp_attached_file` meta is the source of truth for which folder each attachment belongs to.

**Security:** All paths validated to resolve within `wp_upload_dir()['basedir']` before any operation. Any path traversal attempt returns `403`.

---

## Move Attachment Flow (`class-attachment-mover.php`)

1. Read `_wp_attachment_metadata` to resolve all generated image sizes
2. Move original file to destination folder on disk
3. Move all generated sizes to destination folder on disk
4. Update `_wp_attached_file` meta to new relative path
5. Update all paths inside `_wp_attachment_metadata`
6. Replace old URL with new URL in `post_content` across all posts (batched, max 500 posts per request to avoid timeout)

**On partial failure:** If post content updates fail after files have moved, the plugin logs a `_doing_it_wrong()` notice and continues — rolling back a partial filesystem move is more dangerous than a missed URL update. The file and attachment meta will be correct; the edge-case missed URL can be caught by a search-replace tool.

---

## Delete Folder Flow

1. REST `DELETE /folders` receives `action: move | delete`
2. If `move`: all attachments in folder (and subfolders) are moved to `destination_path` via `Attachment_Mover`; then `rmdir` the now-empty folder
3. If `delete`: WordPress `wp_delete_attachment()` called for each attachment (removes files + DB records); then `rmdir`
4. Frontend enforces a two-step confirmation dialog before calling this endpoint

---

## React Frontend

### Injection Points

`class-admin.php` enqueues the React bundle on:
- `upload.php` (Media Library grid view)
- Any page that loads the media modal (`wp_enqueue_media()`)

### Component Structure

```
<FolderPanel>
  ├── <FolderTree>           # Recursive folder tree
  │   └── <FolderNode>       # Single folder: click to filter, drag target, context menu
  ├── <AddFolderButton>      # Inline input → POST /folders
  └── <DeleteFolderDialog>   # Move-or-delete choice + confirmation step
```

### Behaviour

- **Click folder** → filters Media Library to show only that folder's attachments (custom `post_where` filter on PHP side keyed to `?media_folder=` query param)
- **Drag attachment onto folder** → `POST /attachments/{id}/move`
- **Add folder** → inline text input appears, `Enter` submits → `POST /folders`
- **Right-click / "..." on folder** → context menu: Rename, Delete
- **Rename** → inline text input, `Enter` submits → `PATCH /folders`
- **Delete** → opens `<DeleteFolderDialog>` → user chooses move or delete → confirmation → `DELETE /folders`

### State Management

`useState` + `useEffect` only. Folder tree fetched once on mount (`GET /folders`), updated optimistically on mutations. No Redux, no external state library.

### Styling

`@wordpress/components` for all interactive elements (buttons, modals, text inputs, notices). Custom CSS only for folder panel layout (width, tree indentation). No custom design system.

---

## Error Handling

- Filesystem failures (`mkdir`, `rename`, file move) return `WP_Error` with descriptive codes: `folder_exists`, `permission_denied`, `move_failed`, `folder_not_empty`
- Path traversal → `403` immediately
- Frontend displays `@wordpress/components` `<Notice>` on REST errors — no silent failures
- All REST responses follow WP REST API error envelope format

---

## Testing

**PHPUnit** (`tests/php/`):
- `Test_Folder_Manager` — covers create, rename, delete, path validation against a temp directory (no full WP bootstrap needed for filesystem logic)
- `Test_Attachment_Mover` — covers meta update logic with WP test suite

**Jest** (`tests/js/`):
- `FolderTree.test.js` — renders nested folders, calls move callback on drag-drop

**Manual test checklist** (`docs/testing.md`):
- Upload into a selected folder — verify file lands at correct path
- Move attachment — verify original + all sizes moved, attachment meta updated, existing post still shows image
- Delete folder with move — verify attachments appear in destination
- Delete folder with delete — verify files + DB records removed
- Nested folder creation and navigation
- Path traversal attempt via REST — verify `403`

---

## Out of Scope (v1)

- Multisite support
- Bulk move of multiple attachments
- Post content URL replacement beyond 500 posts per move (logged, not errored)
- Folder permissions (all managed folders inherit `uploads/` permissions)
