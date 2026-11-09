import { defineModule, page } from '../../lib/modules';
const AuditPage = page(() => import('./AuditPages'), 'AuditPage');
const AuditsPage = page(() => import('./AuditPages'), 'AuditsPage');
const GeneralDescriptionFormPage = page(() => import('./GeneralDescriptionPages'), 'GeneralDescriptionFormPage');
const GeneralDescriptionPage = page(() => import('./GeneralDescriptionPages'), 'GeneralDescriptionPage');
const GeneralDescriptionsPage = page(() => import('./GeneralDescriptionPages'), 'GeneralDescriptionsPage');
const GeneralInvoiceFormPage = page(() => import('./GeneralInvoicePages'), 'GeneralInvoiceFormPage');
const GeneralInvoicePage = page(() => import('./GeneralInvoicePages'), 'GeneralInvoicePage');
const GeneralInvoicesPage = page(() => import('./GeneralInvoicePages'), 'GeneralInvoicesPage');
const SpecificDescriptionFormPage = page(() => import('./SpecificDescriptionPages'), 'SpecificDescriptionFormPage');
const SpecificDescriptionPage = page(() => import('./SpecificDescriptionPages'), 'SpecificDescriptionPage');
const SpecificDescriptionsPage = page(() => import('./SpecificDescriptionPages'), 'SpecificDescriptionsPage');
const UserFormPage = page(() => import('./UserPages'), 'UserFormPage');
const UserPage = page(() => import('./UserPages'), 'UserPage');
const UsersPage = page(() => import('./UserPages'), 'UsersPage');

/** Accounting (general invoices and their descriptions) and system administration (users and audits). */
export default defineModule({
    routes: [
        { path: 'general-invoices', element: <GeneralInvoicesPage /> },
        { path: 'general-invoices/create', element: <GeneralInvoiceFormPage key="create" /> },
        { path: 'general-invoices/:id', element: <GeneralInvoicePage /> },
        { path: 'general-invoices/:id/edit', element: <GeneralInvoiceFormPage key="edit" /> },

        { path: 'general-descriptions', element: <GeneralDescriptionsPage /> },
        { path: 'general-descriptions/create', element: <GeneralDescriptionFormPage key="create" /> },
        { path: 'general-descriptions/:id', element: <GeneralDescriptionPage /> },
        { path: 'general-descriptions/:id/edit', element: <GeneralDescriptionFormPage key="edit" /> },

        { path: 'specific-descriptions', element: <SpecificDescriptionsPage /> },
        { path: 'specific-descriptions/create', element: <SpecificDescriptionFormPage key="create" /> },
        { path: 'specific-descriptions/:id', element: <SpecificDescriptionPage /> },
        { path: 'specific-descriptions/:id/edit', element: <SpecificDescriptionFormPage key="edit" /> },

        { path: 'users', element: <UsersPage /> },
        { path: 'users/create', element: <UserFormPage key="create" /> },
        { path: 'users/:id', element: <UserPage /> },
        { path: 'users/:id/edit', element: <UserFormPage key="edit" /> },

        { path: 'audits', element: <AuditsPage /> },
        { path: 'audits/:id', element: <AuditPage /> },
    ],
});
