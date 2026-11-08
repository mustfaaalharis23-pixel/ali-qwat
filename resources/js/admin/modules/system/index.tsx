import { defineModule } from '../../lib/modules';
import { AuditPage, AuditsPage } from './AuditPages';
import { GeneralDescriptionFormPage, GeneralDescriptionPage, GeneralDescriptionsPage } from './GeneralDescriptionPages';
import { GeneralInvoiceFormPage, GeneralInvoicePage, GeneralInvoicesPage } from './GeneralInvoicePages';
import { SpecificDescriptionFormPage, SpecificDescriptionPage, SpecificDescriptionsPage } from './SpecificDescriptionPages';
import { UserFormPage, UserPage, UsersPage } from './UserPages';

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
