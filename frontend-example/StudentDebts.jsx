import React, { useState, useEffect } from 'react';
import axios from 'axios';

const StudentDebts = ({ studentId }) => {
    const [debts, setDebts] = useState([]);
    const [student, setStudent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [periods, setPeriods] = useState([]);
    const [formData, setFormData] = useState({
        period_id: '',
        concept: '',
        total_amount: '',
        due_date: '',
        description: ''
    });
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (studentId) {
            fetchStudentDebts();
            fetchPeriods();
        }
    }, [studentId]);

    const fetchStudentDebts = async () => {
        try {
            setLoading(true);
            const response = await axios.get(`/api/debts/student/${studentId}`);
            setDebts(response.data.debts);
            setStudent(response.data.student);
        } catch (error) {
            console.error('Error fetching student debts:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchPeriods = async () => {
        try {
            const response = await axios.get('/api/periods');
            setPeriods(response.data);
        } catch (error) {
            console.error('Error fetching periods:', error);
        }
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
        // Clear error when user starts typing
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: ''
            }));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await axios.post('/api/debts', {
                ...formData,
                student_id: studentId
            });
            
            // Reset form and close modal
            setFormData({
                period_id: '',
                concept: '',
                total_amount: '',
                due_date: '',
                description: ''
            });
            setShowCreateForm(false);
            
            // Refresh debts list
            fetchStudentDebts();
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                console.error('Error creating debt:', error);
            }
        } finally {
            setSubmitting(false);
        }
    };

    const getStatusBadge = (status) => {
        const statusConfig = {
            pending: { color: 'bg-yellow-100 text-yellow-800', text: 'Pendiente' },
            partial: { color: 'bg-blue-100 text-blue-800', text: 'Parcial' },
            paid: { color: 'bg-green-100 text-green-800', text: 'Pagado' },
            overdue: { color: 'bg-red-100 text-red-800', text: 'Vencido' }
        };
        
        const config = statusConfig[status] || statusConfig.pending;
        
        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${config.color}`}>
                {config.text}
            </span>
        );
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN'
        }).format(amount);
    };

    const formatDate = (date) => {
        return new Date(date).toLocaleDateString('es-MX');
    };

    const handleCreatePayment = (debtId) => {
        // Redirect to payment creation with debt_id pre-filled
        window.location.href = `/charges/create?student_id=${studentId}&debt_id=${debtId}`;
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-semibold text-gray-900">
                    Adeudos de {student?.firstname} {student?.lastname}
                </h2>
                <button
                    onClick={() => setShowCreateForm(true)}
                    className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                    Crear Adeudo
                </button>
            </div>

            {/* Create Debt Modal */}
            {showCreateForm && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-md">
                        <h3 className="text-lg font-semibold mb-4">Crear Nuevo Adeudo</h3>
                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Período
                                </label>
                                <select
                                    name="period_id"
                                    value={formData.period_id}
                                    onChange={handleInputChange}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                >
                                    <option value="">Seleccionar período</option>
                                    {periods.map(period => (
                                        <option key={period.id} value={period.id}>
                                            {period.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.period_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.period_id[0]}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Concepto
                                </label>
                                <input
                                    type="text"
                                    name="concept"
                                    value={formData.concept}
                                    onChange={handleInputChange}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Ej: Colegiatura Enero 2025"
                                    required
                                />
                                {errors.concept && (
                                    <p className="text-red-500 text-sm mt-1">{errors.concept[0]}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Monto Total
                                </label>
                                <input
                                    type="number"
                                    name="total_amount"
                                    value={formData.total_amount}
                                    onChange={handleInputChange}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="0.00"
                                    step="0.01"
                                    min="0"
                                    required
                                />
                                {errors.total_amount && (
                                    <p className="text-red-500 text-sm mt-1">{errors.total_amount[0]}</p>
                                )}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Fecha de Vencimiento
                                </label>
                                <input
                                    type="date"
                                    name="due_date"
                                    value={formData.due_date}
                                    onChange={handleInputChange}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                />
                                {errors.due_date && (
                                    <p className="text-red-500 text-sm mt-1">{errors.due_date[0]}</p>
                                )}
                            </div>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Descripción (Opcional)
                                </label>
                                <textarea
                                    name="description"
                                    value={formData.description}
                                    onChange={handleInputChange}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    rows="3"
                                    placeholder="Descripción adicional del adeudo"
                                />
                                {errors.description && (
                                    <p className="text-red-500 text-sm mt-1">{errors.description[0]}</p>
                                )}
                            </div>

                            <div className="flex justify-end space-x-3">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateForm(false)}
                                    className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    {submitting ? 'Creando...' : 'Crear Adeudo'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Debts List */}
            {debts.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                    <p>No hay adeudos registrados para este estudiante.</p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Concepto
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Período
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Monto Total
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pagado
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Restante
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Vencimiento
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {debts.map((debt) => (
                                <tr key={debt.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">
                                                {debt.concept}
                                            </div>
                                            {debt.description && (
                                                <div className="text-sm text-gray-500">
                                                    {debt.description}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {debt.period?.name}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {formatCurrency(debt.total_amount)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {formatCurrency(debt.paid_amount)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {formatCurrency(debt.remaining_amount)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {formatDate(debt.due_date)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {getStatusBadge(debt.status)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        {debt.status !== 'paid' && (
                                            <button
                                                onClick={() => handleCreatePayment(debt.id)}
                                                className="text-blue-600 hover:text-blue-900 mr-3"
                                            >
                                                Registrar Pago
                                            </button>
                                        )}
                                        <button className="text-gray-600 hover:text-gray-900">
                                            Ver Detalles
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Summary */}
            {debts.length > 0 && (
                <div className="mt-6 bg-gray-50 rounded-lg p-4">
                    <h3 className="text-lg font-medium text-gray-900 mb-3">Resumen</h3>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total Adeudos</p>
                            <p className="text-lg font-semibold text-gray-900">
                                {formatCurrency(debts.reduce((sum, debt) => sum + parseFloat(debt.total_amount), 0))}
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total Pagado</p>
                            <p className="text-lg font-semibold text-green-600">
                                {formatCurrency(debts.reduce((sum, debt) => sum + parseFloat(debt.paid_amount), 0))}
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Total Pendiente</p>
                            <p className="text-lg font-semibold text-red-600">
                                {formatCurrency(debts.reduce((sum, debt) => sum + parseFloat(debt.remaining_amount), 0))}
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm text-gray-500">Adeudos Vencidos</p>
                            <p className="text-lg font-semibold text-red-600">
                                {debts.filter(debt => debt.status === 'overdue').length}
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default StudentDebts;