import React, { useState, useEffect } from 'react';
import axios from 'axios';

const DebtDetailsModal = ({ debtId, isOpen, onClose }) => {
    const [debt, setDebt] = useState(null);
    const [transactions, setTransactions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const [paymentData, setPaymentData] = useState({
        amount: '',
        payment_method: 'efectivo',
        notes: ''
    });
    const [paymentLoading, setPaymentLoading] = useState(false);

    useEffect(() => {
        if (isOpen && debtId) {
            fetchDebtDetails();
        }
    }, [isOpen, debtId]);

    const fetchDebtDetails = async () => {
        try {
            setLoading(true);
            const [debtResponse, transactionsResponse] = await Promise.all([
                axios.get(`/api/debts/${debtId}`),
                axios.get(`/api/debts/${debtId}/transactions`)
            ]);
            setDebt(debtResponse.data);
            setTransactions(transactionsResponse.data);
        } catch (error) {
            console.error('Error fetching debt details:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePaymentSubmit = async (e) => {
        e.preventDefault();
        
        if (!paymentData.amount || parseFloat(paymentData.amount) <= 0) {
            alert('El monto debe ser mayor a 0');
            return;
        }

        if (parseFloat(paymentData.amount) > debt.remaining_amount) {
            alert('El monto no puede ser mayor al saldo pendiente');
            return;
        }

        setPaymentLoading(true);
        try {
            await axios.post('/api/charges', {
                student_id: debt.student_id,
                debt_id: debt.id,
                transaction_type: 'income',
                amount: parseFloat(paymentData.amount),
                payment_method: paymentData.payment_method,
                notes: paymentData.notes || `Pago de adeudo: ${debt.concept}`
            });

            setPaymentData({ amount: '', payment_method: 'efectivo', notes: '' });
            setShowPaymentForm(false);
            await fetchDebtDetails();
            alert('Pago registrado exitosamente');
        } catch (error) {
            console.error('Error registering payment:', error);
            alert('Error al registrar el pago. Por favor, intente nuevamente.');
        } finally {
            setPaymentLoading(false);
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
        return new Date(date).toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const formatDateTime = (date) => {
        return new Date(date).toLocaleString('es-MX');
    };

    const getPaymentMethodText = (method) => {
        const methods = {
            efectivo: 'Efectivo',
            tarjeta: 'Tarjeta',
            transferencia: 'Transferencia',
            cheque: 'Cheque',
            otro: 'Otro'
        };
        return methods[method] || method;
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200">
                    <h2 className="text-xl font-semibold text-gray-900">
                        Detalles del Adeudo
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {loading ? (
                    <div className="flex justify-center items-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>
                ) : debt ? (
                    <div className="p-6 space-y-6">
                        {/* Debt Information */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-4">
                                <h3 className="text-lg font-medium text-gray-900">Información del Adeudo</h3>
                                <div className="space-y-2">
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Concepto:</span>
                                        <p className="text-sm text-gray-900">{debt.concept}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Descripción:</span>
                                        <p className="text-sm text-gray-900">{debt.description || 'Sin descripción'}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Período:</span>
                                        <p className="text-sm text-gray-900">{debt.period?.name}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Fecha de vencimiento:</span>
                                        <p className="text-sm text-gray-900">{formatDate(debt.due_date)}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Estado:</span>
                                        <div className="mt-1">{getStatusBadge(debt.status)}</div>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="text-lg font-medium text-gray-900">Información del Estudiante</h3>
                                <div className="space-y-2">
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Nombre:</span>
                                        <p className="text-sm text-gray-900">
                                            {debt.student?.firstname} {debt.student?.lastname}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Matrícula:</span>
                                        <p className="text-sm text-gray-900">{debt.student?.matricula}</p>
                                    </div>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Campus:</span>
                                        <p className="text-sm text-gray-900">{debt.student?.campus?.name}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Payment Summary */}
                        <div className="bg-gray-50 rounded-lg p-4">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Resumen de Pagos</h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-500">Monto Total</p>
                                    <p className="text-2xl font-bold text-gray-900">{formatCurrency(debt.total_amount)}</p>
                                </div>
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-500">Monto Pagado</p>
                                    <p className="text-2xl font-bold text-green-600">{formatCurrency(debt.paid_amount)}</p>
                                </div>
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-500">Saldo Pendiente</p>
                                    <p className="text-2xl font-bold text-red-600">{formatCurrency(debt.remaining_amount)}</p>
                                </div>
                            </div>
                            {debt.remaining_amount > 0 && (
                                <div className="mt-4 text-center">
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            className="bg-green-600 h-2 rounded-full" 
                                            style={{ width: `${(debt.paid_amount / debt.total_amount) * 100}%` }}
                                        ></div>
                                    </div>
                                    <p className="text-sm text-gray-600 mt-2">
                                        {((debt.paid_amount / debt.total_amount) * 100).toFixed(1)}% pagado
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Payment Form */}
                        {debt.status !== 'paid' && (
                            <div>
                                {!showPaymentForm ? (
                                    <button
                                        onClick={() => setShowPaymentForm(true)}
                                        className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                        Registrar Pago
                                    </button>
                                ) : (
                                    <div className="bg-blue-50 rounded-lg p-4">
                                        <h4 className="text-lg font-medium text-gray-900 mb-4">Registrar Nuevo Pago</h4>
                                        <form onSubmit={handlePaymentSubmit} className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Monto *
                                                    </label>
                                                    <div className="relative">
                                                        <span className="absolute left-3 top-2 text-gray-500">$</span>
                                                        <input
                                                            type="number"
                                                            value={paymentData.amount}
                                                            onChange={(e) => setPaymentData(prev => ({ ...prev, amount: e.target.value }))}
                                                            step="0.01"
                                                            min="0.01"
                                                            max={debt.remaining_amount}
                                                            placeholder="0.00"
                                                            className="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                            required
                                                        />
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Máximo: {formatCurrency(debt.remaining_amount)}
                                                    </p>
                                                </div>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Método de Pago *
                                                    </label>
                                                    <select
                                                        value={paymentData.payment_method}
                                                        onChange={(e) => setPaymentData(prev => ({ ...prev, payment_method: e.target.value }))}
                                                        className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                        required
                                                    >
                                                        <option value="efectivo">Efectivo</option>
                                                        <option value="tarjeta">Tarjeta</option>
                                                        <option value="transferencia">Transferencia</option>
                                                        <option value="cheque">Cheque</option>
                                                        <option value="otro">Otro</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Notas
                                                </label>
                                                <textarea
                                                    value={paymentData.notes}
                                                    onChange={(e) => setPaymentData(prev => ({ ...prev, notes: e.target.value }))}
                                                    rows={2}
                                                    placeholder="Notas adicionales del pago (opcional)..."
                                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                />
                                            </div>
                                            <div className="flex space-x-3">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowPaymentForm(false);
                                                        setPaymentData({ amount: '', payment_method: 'efectivo', notes: '' });
                                                    }}
                                                    className="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-400 transition-colors"
                                                >
                                                    Cancelar
                                                </button>
                                                <button
                                                    type="submit"
                                                    disabled={paymentLoading}
                                                    className="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors flex items-center justify-center"
                                                >
                                                    {paymentLoading && (
                                                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                    )}
                                                    {paymentLoading ? 'Procesando...' : 'Registrar Pago'}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Transactions History */}
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Historial de Transacciones ({transactions.length})
                            </h3>
                            {transactions.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    <p>No hay transacciones registradas para este adeudo.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Fecha
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Tipo
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Monto
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Método
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Notas
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {transactions.map((transaction) => (
                                                <tr key={transaction.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {formatDateTime(transaction.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                                            transaction.transaction_type === 'income' 
                                                                ? 'bg-green-100 text-green-800' 
                                                                : 'bg-red-100 text-red-800'
                                                        }`}>
                                                            {transaction.transaction_type === 'income' ? 'Ingreso' : 'Egreso'}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <span className={transaction.transaction_type === 'income' ? 'text-green-600' : 'text-red-600'}>
                                                            {transaction.transaction_type === 'income' ? '+' : '-'}{formatCurrency(transaction.amount)}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {getPaymentMethodText(transaction.payment_method)}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-900">
                                                        {transaction.notes || '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="text-center py-8 text-gray-500">
                        <p>No se pudo cargar la información del adeudo.</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DebtDetailsModal;