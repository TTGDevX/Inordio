<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    //
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Help &amp; guide</h1>
            <p class="mt-1 text-gray-500">How Inordio works, and how to get the most out of it.</p>
        </div>

        {{-- Contents --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Contents</h2>
            <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1 text-sm text-indigo-600">
                <li><a href="#start" class="hover:underline">Getting started &amp; roles</a></li>
                <li><a href="#flow" class="hover:underline">The core workflow</a></li>
                <li><a href="#inventory" class="hover:underline">Inventory, locations &amp; picking</a></li>
                <li><a href="#assets" class="hover:underline">Serialized assets</a></li>
                <li><a href="#schedule" class="hover:underline">Scheduling &amp; the ops board</a></li>
                <li><a href="#field" class="hover:underline">Field work: photos, notes, checklists, sign-off</a></li>
                <li><a href="#agreements" class="hover:underline">Service agreements (recurring)</a></li>
                <li><a href="#paid" class="hover:underline">Getting paid</a></li>
                <li><a href="#settings" class="hover:underline">Settings, branding &amp; email</a></li>
                <li><a href="#api" class="hover:underline">API &amp; integrations</a></li>
            </ul>
        </div>

        <section id="start" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Getting started &amp; roles</h2>
            <p class="text-gray-700">Everyone signs in to the same company workspace, but what they can do depends on their role. From most to least access: <strong>Owner</strong> and <strong>Admin</strong> (everything, including settings), <strong>Office</strong> (quotes, jobs, customers, inventory, invoicing), <strong>Technician</strong> (works jobs in the field — start/complete, pick stock, log time, photos, sign-off), and <strong>Viewer</strong> (read-only, e.g. an accountant).</p>
            <p class="text-gray-700">First steps for an admin: fill in <a href="{{ route('settings.company') }}" wire:navigate class="text-indigo-600 hover:underline">Company settings</a> (name, logo, tax number, payment terms), then add your locations, inventory, and first customer.</p>
        </section>

        <section id="flow" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">The core workflow</h2>
            <p class="text-gray-700">The heart of Inordio is a single flow: <strong>Quote → Job → Pick → Complete → Invoice → Payment</strong>.</p>
            <ol class="list-decimal ml-5 text-gray-700 space-y-1">
                <li>Build a <strong>Quote</strong> for a customer with line items (catalogue parts or custom lines) and send it. When approved, convert it to a Job in one click.</li>
                <li>The <strong>Job</strong> carries the work: schedule it, assign a technician, generate a pick list, and complete it in the field.</li>
                <li><strong>Pick</strong> the parts from a warehouse onto the truck; completing the job consumes them so stock stays accurate.</li>
                <li>Raise an <strong>Invoice</strong> from the finished job — parts and logged labour flow onto it, with tax snapshotted. Bill it fully or in stages (deposit / progress / final).</li>
                <li>Record a <strong>Payment</strong> and optionally email a receipt.</li>
            </ol>
        </section>

        <section id="inventory" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Inventory, locations &amp; picking</h2>
            <p class="text-gray-700">Inordio tracks <strong>real quantities at real places</strong> — warehouses, trucks, and job sites. Every receipt, transfer, pick, and adjustment is written to a movement ledger you can review (the <a href="{{ route('movements.index') }}" wire:navigate class="text-indigo-600 hover:underline">Movement log</a>, or per-item on its page). Receiving stock records the supplier and unit cost and rolls a weighted-average cost, so job margins are accurate.</p>
            <p class="text-gray-700">Set a minimum per location and the <a href="{{ route('inventory.reorder') }}" wire:navigate class="text-indigo-600 hover:underline">Reorder view</a> shows what to buy, grouped by preferred supplier, alongside anything back-ordered from short picks. Print <strong>QR labels</strong> for items, bins, and trucks from the inventory and locations pages.</p>
            <p class="text-gray-700"><strong>Short picks:</strong> if there isn't enough on the truck, pick what you have and flag the rest — it's recorded as a back-order and surfaces on the reorder view.</p>
        </section>

        <section id="assets" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Serialized assets</h2>
            <p class="text-gray-700">For individually-tracked equipment (by serial number), <a href="{{ route('assets.index') }}" wire:navigate class="text-indigo-600 hover:underline">Assets</a> lets you nest units inside each other like building blocks — a drive inside a server inside a rack. Assemble and disassemble are recorded as history, and a nested part inherits its location from the top-level unit, so moving the rack moves everything inside it.</p>
        </section>

        <section id="schedule" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Scheduling &amp; the ops board</h2>
            <p class="text-gray-700">The <a href="{{ route('jobs.schedule') }}" wire:navigate class="text-indigo-600 hover:underline">Schedule</a> groups active jobs by date (with Today/Overdue flags and a "needs scheduling" bucket) and filters by technician. The <a href="{{ route('board') }}" wire:navigate class="text-indigo-600 hover:underline">Ops board</a> is a big, auto-refreshing view of active jobs and the picking queue — point a shop TV at it.</p>
        </section>

        <section id="field" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Field work</h2>
            <p class="text-gray-700">On a job, technicians can attach <strong>photos</strong> (before/after), post timestamped <strong>notes</strong>, fill out <strong>inspection checklists</strong> (pass/fail/N-A with notes — from reusable templates you set up under Checklists), log <strong>labour hours</strong> (which bill onto the invoice), and capture a <strong>customer sign-off</strong> signature at completion.</p>
        </section>

        <section id="agreements" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Service agreements (recurring)</h2>
            <p class="text-gray-700">For contract maintenance, a <a href="{{ route('agreements.index') }}" wire:navigate class="text-indigo-600 hover:underline">Service agreement</a> spawns scheduled jobs on a cadence (monthly, quarterly, etc.), copying a line-item template onto each visit. You can also "Generate now" on demand.</p>
        </section>

        <section id="paid" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Getting paid</h2>
            <p class="text-gray-700">Invoices snapshot Canadian tax at issue time based on the customer's province. Record payments by cash, cheque, <strong>Interac e-Transfer</strong>, <strong>EFT / direct deposit</strong>, or card. Since Interac e-Transfers auto-deposit to your bank, you simply record the deposit against the invoice (add the reference), and can email the customer a receipt.</p>
            <p class="text-gray-700">Send a customer their whole <strong>account statement</strong> from the customer page, and pull a <strong>GST/HST collected</strong> summary for any period from <a href="{{ route('reports.index') }}" wire:navigate class="text-indigo-600 hover:underline">Reports</a> for filing.</p>
        </section>

        <section id="settings" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">Settings, branding &amp; email</h2>
            <p class="text-gray-700">In <a href="{{ route('settings.company') }}" wire:navigate class="text-indigo-600 hover:underline">Company settings</a> (admins) you set your logo, accent colour, footer, terms &amp; conditions, GST/HST number, document numbering, default labour rate, and your own outgoing email (SMTP) server. Under <a href="{{ route('settings.templates') }}" wire:navigate class="text-indigo-600 hover:underline">Email templates</a> you can customise the subject and wording of invoice, quote, and receipt emails using placeholder tokens (like <code class="bg-gray-100 px-1 rounded">customer_name</code> and <code class="bg-gray-100 px-1 rounded">invoice_number</code>) — the editor lists the ones available.</p>
        </section>

        <section id="api" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-2">
            <h2 class="text-lg font-semibold text-gray-800">API &amp; integrations</h2>
            <p class="text-gray-700">Admins can create <a href="{{ route('settings.api-tokens') }}" wire:navigate class="text-indigo-600 hover:underline">API tokens</a> to connect external tools. Use a token as a <code class="bg-gray-100 px-1 rounded">Bearer</code> credential against <code class="bg-gray-100 px-1 rounded">/api/v1</code> — e.g. <code class="bg-gray-100 px-1 rounded">/api/v1/invoices</code>, <code class="bg-gray-100 px-1 rounded">/api/v1/jobs</code>. Everything is scoped to your company.</p>
        </section>

        <p class="text-center text-sm text-gray-400">Still stuck? Contact your Inordio administrator.</p>
    </div>
</div>
