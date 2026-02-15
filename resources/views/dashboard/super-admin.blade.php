<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Super Admin Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded p-6 mb-6">
                <h3 class="text-lg font-bold mb-2">Welcome, Super Admin</h3>
                <p>You can manage schools and system-wide features.</p>
            </div>

            <div class="bg-white shadow rounded p-6">
                <a href="/schools" class="text-blue-600 underline">
                    Manage Schools
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
