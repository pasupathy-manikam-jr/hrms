import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileText,
    Folder,
    FolderPlus,
    LayoutGrid,
    List,
    Search,
    SquarePen,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { applyFilters } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { cn, toUrl } from '@/lib/utils';
import { dashboard, mediaLibrary } from '@/routes';
import mediaRoutes from '@/routes/media-library';
import directoryRoutes from '@/routes/media-library/directories';
import type { Paginated, TableFilters } from '@/types';

type Directory = {
    id: number;
    name: string;
    created_by: number | null;
    media_count: number;
};

type MediaItem = {
    id: number;
    name: string;
    file_name: string;
    file_type: string | null;
    file_size: number;
    directory_id: number | null;
    is_image: boolean;
    created_at: string;
    directory: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
};

const fileSize = (bytes: number) =>
    bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

export default function MediaLibrary({
    media,
    directories,
    totalCount,
    unfiledCount,
    uploadTypes,
    uploadMaxKb,
    filters,
}: {
    media: Paginated<MediaItem>;
    directories: Directory[];
    totalCount: number;
    unfiledCount: number;
    uploadTypes: string[];
    uploadMaxKb: number;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = mediaLibrary();
    const view = filters.view === 'list' ? 'list' : 'grid';
    const [search, setSearch] = useState(filters.search ?? '');
    const [uploadOpen, setUploadOpen] = useState(false);
    const [editing, setEditing] = useState<MediaItem | null>(null);
    const [deleting, setDeleting] = useState<MediaItem | null>(null);
    const [previewing, setPreviewing] = useState<MediaItem | null>(null);
    const [folderOpen, setFolderOpen] = useState(false);
    const [editingFolder, setEditingFolder] = useState<Directory | null>(null);
    const [deletingFolder, setDeletingFolder] = useState<Directory | null>(
        null,
    );
    const uploadForm = useForm({
        files: [] as File[],
        directory_id: '' as number | string,
    });
    const editForm = useForm({
        name: '',
        directory_id: '' as number | string,
    });
    const folderForm = useForm({ name: '' });

    const openUpload = () => {
        uploadForm.clearErrors();
        uploadForm.setData({
            files: [],
            directory_id:
                filters.directory_id && filters.directory_id !== 'none'
                    ? filters.directory_id
                    : '',
        });
        setUploadOpen(true);
    };

    const openEdit = (item: MediaItem) => {
        editForm.clearErrors();
        editForm.setData({
            name: item.name,
            directory_id: item.directory_id ?? '',
        });
        setEditing(item);
    };

    const openFolder = (directory: Directory | null) => {
        folderForm.clearErrors();
        folderForm.setData('name', directory?.name ?? '');
        setEditingFolder(directory);
        setFolderOpen(true);
    };

    const uploadErrors = Object.entries(uploadForm.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message);

    const actions = (item: MediaItem) => (
        <>
            {item.is_image && can('view-media') && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Preview')}
                    onClick={() => setPreviewing(item)}
                >
                    <Eye />
                </Button>
            )}
            {can('download-media') && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Download')}
                    asChild
                >
                    <a href={mediaRoutes.download(item.id).url}>
                        <Download />
                    </a>
                </Button>
            )}
            {can('edit-media') && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Rename or Move')}
                    onClick={() => openEdit(item)}
                >
                    <SquarePen />
                </Button>
            )}
            {can('delete-media') && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Delete')}
                    onClick={() => setDeleting(item)}
                >
                    <Trash2 />
                </Button>
            )}
        </>
    );

    const thumbnail = (item: MediaItem, className: string) =>
        item.is_image && can('view-media') ? (
            <img
                src={mediaRoutes.preview(item.id).url}
                alt={item.name}
                loading="lazy"
                className={cn('object-cover', className)}
            />
        ) : (
            <div
                className={cn(
                    'flex items-center justify-center bg-muted text-muted-foreground',
                    className,
                )}
            >
                <FileText className="size-1/3 max-h-12 max-w-12" />
            </div>
        );

    const columns: Column<MediaItem>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (item) => (
                <div className="flex items-center gap-3">
                    {thumbnail(item, 'size-10 shrink-0 rounded')}
                    <div className="min-w-0">
                        <div className="truncate font-medium">{item.name}</div>
                        <div className="truncate text-xs text-muted-foreground">
                            {item.file_name}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'directory',
            label: 'Folder',
            render: (item) => item.directory?.name ?? '-',
        },
        {
            key: 'file_size',
            label: 'Size',
            sortable: true,
            render: (item) => fileSize(item.file_size),
        },
        {
            key: 'created_at',
            label: 'Uploaded',
            sortable: true,
            render: (item) => <DateCell value={item.created_at} />,
        },
    ];

    const folderButton = (
        label: string,
        count: number,
        value: string | undefined,
    ) => (
        <button
            type="button"
            onClick={() => applyFilters(url, filters, { directory_id: value })}
            className={cn(
                'flex w-full min-w-0 flex-1 items-center gap-2 rounded-md px-3 py-2 text-start text-sm hover:bg-muted',
                (filters.directory_id ?? undefined) === value &&
                    'bg-muted font-medium',
            )}
        >
            <Folder className="size-4 shrink-0 text-muted-foreground" />
            <span className="flex-1 truncate">{label}</span>
            <span className="text-xs text-muted-foreground">{count}</span>
        </button>
    );

    return (
        <>
            <Head title={t('Media Library')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Media Library"
                    description="Upload, organise and share files."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('create-media-directories') && (
                                <Button
                                    variant="outline"
                                    onClick={() => openFolder(null)}
                                >
                                    <FolderPlus /> {t('New Folder')}
                                </Button>
                            )}
                            {can('create-media') && (
                                <Button onClick={openUpload}>
                                    <Upload /> {t('Upload Files')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
                    <aside className="grid content-start gap-1 rounded-xl border bg-card p-2 shadow-sm">
                        {folderButton(t('All Files'), totalCount, undefined)}
                        {folderButton(t('Unfiled'), unfiledCount, 'none')}
                        {directories.map((directory) => (
                            <div
                                key={directory.id}
                                className="group flex items-center"
                            >
                                {folderButton(
                                    directory.name,
                                    directory.media_count,
                                    String(directory.id),
                                )}
                                {can('edit-media-directories') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 shrink-0"
                                        aria-label={t('Rename Folder')}
                                        onClick={() => openFolder(directory)}
                                    >
                                        <SquarePen className="size-3.5" />
                                    </Button>
                                )}
                                {can('delete-media-directories') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 shrink-0"
                                        aria-label={t('Delete Folder')}
                                        onClick={() =>
                                            setDeletingFolder(directory)
                                        }
                                    >
                                        <Trash2 className="size-3.5 text-destructive" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </aside>

                    <div className="grid content-start gap-4">
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <div
                                className="flex rounded-md border p-0.5"
                                role="group"
                                aria-label={t('View')}
                            >
                                {(['grid', 'list'] as const).map((mode) => (
                                    <Button
                                        key={mode}
                                        variant={
                                            view === mode
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="icon"
                                        className="size-8"
                                        aria-pressed={view === mode}
                                        aria-label={t(
                                            mode === 'grid'
                                                ? 'Grid view'
                                                : 'List view',
                                        )}
                                        onClick={() =>
                                            router.get(
                                                toUrl(url),
                                                { ...filters, view: mode },
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    replace: true,
                                                },
                                            )
                                        }
                                    >
                                        {mode === 'grid' ? (
                                            <LayoutGrid />
                                        ) : (
                                            <List />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        {view === 'list' ? (
                            <DataTable
                                data={media}
                                columns={columns}
                                filters={filters}
                                url={url}
                                actions={actions}
                                emptyMessage="No files found."
                            />
                        ) : (
                            <>
                                <form
                                    noValidate
                                    className="relative w-full max-w-xs"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        applyFilters(url, filters, { search });
                                    }}
                                >
                                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder={t('Search...')}
                                        aria-label={t('Search')}
                                        className="ps-9"
                                    />
                                </form>
                                {media.data.length === 0 ? (
                                    <div className="rounded-xl border py-12 text-center text-sm text-muted-foreground">
                                        {t('No files found.')}
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                                        {media.data.map((item) => (
                                            <div
                                                key={item.id}
                                                className="overflow-hidden rounded-xl border bg-card shadow-sm"
                                            >
                                                {thumbnail(
                                                    item,
                                                    'aspect-video w-full',
                                                )}
                                                <div className="grid gap-1 p-3">
                                                    <div
                                                        className="truncate text-sm font-medium"
                                                        title={item.file_name}
                                                    >
                                                        {item.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {fileSize(
                                                            item.file_size,
                                                        )}
                                                        {item.directory &&
                                                            ` · ${item.directory.name}`}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        <DateCell
                                                            value={
                                                                item.created_at
                                                            }
                                                        />
                                                    </div>
                                                    <div className="-ms-2 flex flex-wrap">
                                                        {actions(item)}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                {media.last_page > 1 && (
                                    <div className="flex items-center justify-end gap-1 text-sm text-muted-foreground">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={media.current_page <= 1}
                                            aria-label={t('Previous')}
                                            onClick={() =>
                                                router.get(
                                                    toUrl(url),
                                                    {
                                                        ...filters,
                                                        page:
                                                            media.current_page -
                                                            1,
                                                    },
                                                    { preserveState: true },
                                                )
                                            }
                                        >
                                            <ChevronLeft className="rtl:rotate-180" />
                                        </Button>
                                        <span className="px-2">
                                            {media.current_page} /{' '}
                                            {media.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                media.current_page >=
                                                media.last_page
                                            }
                                            aria-label={t('Next')}
                                            onClick={() =>
                                                router.get(
                                                    toUrl(url),
                                                    {
                                                        ...filters,
                                                        page:
                                                            media.current_page +
                                                            1,
                                                    },
                                                    { preserveState: true },
                                                )
                                            }
                                        >
                                            <ChevronRight className="rtl:rotate-180" />
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>

            <FormDialog
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                title="Upload Files"
                description={`${uploadTypes.join(', ').toUpperCase()} · ${t('max :size per file', { size: fileSize(uploadMaxKb * 1024) })}`}
                onSubmit={(e) => {
                    e.preventDefault();
                    uploadForm.post(mediaRoutes.store().url, {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => setUploadOpen(false),
                    });
                }}
                processing={uploadForm.processing}
                submitLabel="Upload"
            >
                <div className="grid gap-2">
                    <Label htmlFor="media-files">
                        {t('Files')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="media-files"
                        type="file"
                        multiple
                        required
                        accept={uploadTypes.map((ext) => `.${ext}`).join(',')}
                        onChange={(e) =>
                            uploadForm.setData(
                                'files',
                                Array.from(e.target.files ?? []),
                            )
                        }
                    />
                    {uploadErrors.map((message) => (
                        <InputError key={message} message={message} />
                    ))}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="media-upload-folder">{t('Folder')}</Label>
                    <SelectField
                        id="media-upload-folder"
                        value={uploadForm.data.directory_id}
                        onChange={(e) =>
                            uploadForm.setData('directory_id', e.target.value)
                        }
                    >
                        <option value="">{t('No folder')}</option>
                        {directories.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={uploadForm.errors.directory_id} />
                </div>
                {uploadForm.progress && (
                    <progress
                        className="w-full"
                        value={uploadForm.progress.percentage}
                        max="100"
                    />
                )}
            </FormDialog>

            <FormDialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title="Rename or Move"
                description={editing?.file_name}
                onSubmit={(e) => {
                    e.preventDefault();

                    if (editing) {
                        editForm.submit(mediaRoutes.update(editing.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(null),
                        });
                    }
                }}
                processing={editForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="media-name">
                        {t('Name')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="media-name"
                        required
                        value={editForm.data.name}
                        onChange={(e) =>
                            editForm.setData('name', e.target.value)
                        }
                    />
                    <InputError message={editForm.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="media-folder">{t('Folder')}</Label>
                    <SelectField
                        id="media-folder"
                        value={editForm.data.directory_id}
                        onChange={(e) =>
                            editForm.setData('directory_id', e.target.value)
                        }
                    >
                        <option value="">{t('No folder')}</option>
                        {directories.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={editForm.errors.directory_id} />
                </div>
            </FormDialog>

            <FormDialog
                open={folderOpen}
                onOpenChange={setFolderOpen}
                title={editingFolder ? 'Rename Folder' : 'New Folder'}
                onSubmit={(e) => {
                    e.preventDefault();
                    folderForm.submit(
                        editingFolder
                            ? directoryRoutes.update(editingFolder.id)
                            : directoryRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFolderOpen(false),
                        },
                    );
                }}
                processing={folderForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="folder-name">
                        {t('Name')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="folder-name"
                        required
                        value={folderForm.data.name}
                        onChange={(e) =>
                            folderForm.setData('name', e.target.value)
                        }
                    />
                    <InputError message={folderForm.errors.name} />
                </div>
            </FormDialog>

            <Dialog
                open={previewing !== null}
                onOpenChange={(open) => !open && setPreviewing(null)}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogTitle>{previewing?.name}</DialogTitle>
                    {previewing && (
                        <img
                            src={mediaRoutes.preview(previewing.id).url}
                            alt={previewing.name}
                            className="max-h-[70dvh] w-full rounded object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This file will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(mediaRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />

            <ConfirmDialog
                open={deletingFolder !== null}
                onOpenChange={(open) => !open && setDeletingFolder(null)}
                description="This folder will be deleted. Its files are kept and become unfiled."
                onConfirm={() =>
                    deletingFolder &&
                    router.delete(directoryRoutes.destroy(deletingFolder.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeletingFolder(null),
                    })
                }
            />
        </>
    );
}

MediaLibrary.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Media Library', href: mediaLibrary() },
    ],
};
