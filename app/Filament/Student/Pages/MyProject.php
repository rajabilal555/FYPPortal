<?php

namespace App\Filament\Student\Pages;

use App\Actions\InviteProjectMember;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectInvite;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class MyProject extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static string $view = 'filament.student.pages.my-project';

    public ?Project $project;

    public function boot(): void
    {
        $this->project = auth()->user()->project;
    }

    public function getHeader(): ?View
    {
        return view('components.project-header', [
            'project' => $this->project,
            'actions' => $this->getHeaderActions()
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dodo')
                ->action(function () {
                    Student::find(auth()->id())->notify(Notification::make()
                        ->title('YOYOYOYo hows it going broooooz')
                        ->body("I'm just testing this out")
                        ->toDatabase());
                }),

            CreateAction::make('create-project')
                ->color('success')
                ->icon('heroicon-m-sparkles')
                ->size('lg')
                ->hidden(fn() => $this->project != null)
                ->label(__('Create Project'))
                ->model(Project::class)
                ->using(function (array $data): Project {
                    $project = Project::create($data);
                    $project->students()->save(auth()->user());
                    return $project;
                })
                ->steps([
                    Step::make('Name')
                        ->description('Give your project a unique name')
                        ->schema([
                            TextInput::make('name')
                                ->required(),
                        ]),
                    Step::make('Description')
                        ->description('Add some extra details')
                        ->schema([
                            MarkdownEditor::make('description'),
                        ]),
                ]),

            EditAction::make('edit-project')
                ->color('primary')
                ->icon('heroicon-o-pencil')
                ->size('lg')
                ->hidden(fn() => $this->project == null)
                ->label(__('Edit Project'))
                ->model(Project::class)
                ->record($this->project)
                ->steps([
                    Step::make('Name')
                        ->description('Give your project a unique name')
                        ->schema([
                            TextInput::make('name')
                                ->required(),
                        ]),
                    Step::make('Description')
                        ->description('Add some extra details')
                        ->schema([
                            MarkdownEditor::make('description'),
                        ]),
                ]),
        ];
    }

    public function inviteAdvisorAction(): Action
    {
        return Action::make('inviteAdvisorAction')
            ->icon('heroicon-o-plus')
            ->label('Invite Advisor')
            ->extraAttributes([
                'class' => 'mt-5 w-full',
            ])
            ->action(function () {
                $this->redirect('/');
            });
    }


    public function inviteStudentAction(): Action
    {
        return Action::make('inviteStudentAction')
            ->label('Invite Student')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->extraAttributes([
                'class' => 'mt-5 w-full',
            ])
            ->form([
                Select::make('student_id')
                    ->label('Student')
                    ->placeholder('Select a student')
                    ->options(Student::whereNotIn('id', $this->project->students->pluck('id'))->get()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                MarkdownEditor::make('message')
                    ->required(),
            ])
            ->action(function (array $data) {
               app(InviteProjectMember::class)->handle($this->project, $data);
            });
    }

    public function downloadFileAction(): Action
    {
        return Action::make('downloadFileAction')
            ->icon('heroicon-o-arrow-down-tray')
            ->iconButton()
            ->action(function (array $arguments) {
                $file = ProjectFile::find($arguments['file']);
                return response()->streamDownload(function () use ($file) {
                    echo Storage::disk($file->storage_disk)->get($file->storage_path);
                }, $file->name . '.' . $file->getFileType());
            });
    }

    public function deleteFileAction(): Action
    {
        return Action::make('deleteFileAction')
            ->label('Delete File')
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $file = ProjectFile::find($arguments['file']);

                $file?->delete();
            });
    }

    public function uploadFileAction(): Action
    {
        return Action::make('uploadFileAction')
            ->label('Upload File')
            ->icon('heroicon-o-arrow-up-tray')
            ->record($this->project)
            ->extraAttributes([
                'class' => 'mt-5 w-full',
            ])
            ->form([
                TextInput::make('document_name')
                    ->label('Document Name')
                    ->required(),

                FileUpload::make('document')
                    ->label('Document')
                    ->disk('private')
                    ->directory('project-files')
                    ->visibility('private')
                    ->required(),
            ])
            ->action(function (Project $project, array $data) {
                return $project->files()->create([
                    'name' => $data['document_name'],
                    'storage_path' => $data['document'],
                    'storage_disk' => 'private',
                    'student_id' => auth()->id(),
                ]);
            });
    }


}
