<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $category->exists ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST"
                      action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}"
                      class="space-y-6">
                    @csrf
                    @if ($category->exists)
                        @method('PUT')
                    @endif

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $category->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Beschreibung (optional)" />
                        <textarea id="description" name="description" rows="4"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $category->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Speichern</x-primary-button>
                        <a href="{{ $category->exists ? route('categories.show', $category) : route('categories.index') }}"
                           class="text-sm text-gray-600 hover:text-gray-900">Abbrechen</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
