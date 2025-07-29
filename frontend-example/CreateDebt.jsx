import React, { useState, useEffect } from 'react';
import axios from 'axios';

const CreateDebt = ({ studentId = null, onDebtCreated = null }) => {
    const [formData, setFormData] = useState({
        student_id: studentId || '',
        period_id: '',
        concept: '',
        total_amount: '',
        due_date: '',
        description: ''
    });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [students, setStudents] = useState([]);
    const [periods, setPeriods] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [showStudentSearch, setShowStudentSearch] = useState(!studentId);

    useEffect(() => {
        fetchPeriods();
        if (!studentId) {
            fetchStudents();
        }
    }, [studentId]);

    useEffect(() => {
        if (searchTerm && !studentId) {
            const delayedSearch = setTimeout(() => {
                fetchStudents(searchTerm);
            }, 300);
            return () => clearTimeout(delayedSearch);
        }
    }, [searchTerm, studentId]);

    const fetchStudents = async (search = '') => {
        try {
            const params = search ? { search } : {};
            const response = await axios.get('/api/students', { params });
            setStudents(response.data.data || response.data);
        } catch (error) {
            console.error('Error fetching students:', error);
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
        
        if (errors[name]) {
            setErrors(prev => ({
                ...prev,
                [name]: ''
            }));
        }
    };

    const handleStudentSelect = (student) => {
        setFormData(prev => ({
            ...prev,
            student_id: student.id
        }));
        setShowStudentSearch(false);
        setSearchTerm(`${student.firstname} ${student.lastname} - ${student.matricula}`);
    };

    const handleStudentSearchChange = (e) => {
        setSearchTerm(e.target.value);
        if (!e.target.value) {
            setFormData(prev => ({ ...prev, student_id: '' }));
            setShowStudentSearch(true);
        }
    };

    const validateForm = () => {
        const newErrors = {};

        if (!formData.student_id) {
            newErrors.student_id = 'Debe seleccionar un estudiante';
        }
        if (!formData.period_id) {
            newErrors.period_id = 'Debe seleccionar un período';
        }
        if (!formData.concept.trim()) {
            newErrors.concept = 'El concepto es requerido';
        }
        if (!formData.total_amount || parseFloat(formData.total_amount) <= 0) {
            newErrors.total_amount = 'El monto debe ser mayor a 0';
        }
        if (!formData.due_date) {
            newErrors.due_date = 'La fecha de vencimiento es requerida';
        } else {
            const dueDate = new Date(formData.due_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (dueDate < today) {
                newErrors.due_date = 'La fecha de vencimiento no puede ser anterior a hoy';
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }

        setLoading(true);
        try {
            const response = await axios.post('/api/debts', {
                ...formData,
                total_amount: parseFloat(formData.total_amount)
            });

            if (onDebtCreated) {
                onDebtCreated(response.data);
            }

            // Reset form if not in modal mode
            if (!onDebtCreated) {
                setFormData({
                    student_id: studentId || '',
                    period_id: '',
                    concept: '',
                    total_amount: '',
                    due_date: '',
                    description: ''
                });
                setSearchTerm('');
                setShowStudentSearch(!studentId);
            }

            alert('Adeudo creado exitosamente');
        } catch (error) {
            console.error('Error creating debt:', error);
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                alert('Error al crear el adeudo. Por favor, intente nuevamente.');
            }
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN'
        }).format(amount);
    };

    const getMinDate = () => {
        const today = new Date();
        return today.toISOString().split('T')[0];
    };

    return (
        <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6">
                Crear Nuevo Adeudo
            </h2>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Student Selection */}
                {!studentId && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Estudiante *
                        </label>
                        <div className="relative">
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={handleStudentSearchChange}
                                onFocus={() => setShowStudentSearch(true)}
                                placeholder="Buscar estudiante por nombre o matrícula..."
                                className={`w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                    errors.student_id ? 'border-red-500' : 'border-gray-300'
                                }`}
                            />
                            {showStudentSearch && students.length > 0 && (
                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    {students.map(student => (
                                        <div
                                            key={student.id}
                                            onClick={() => handleStudentSelect(student)}
                                            className="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                                        >
                                            <div className="font-medium text-gray-900">
                                                {student.firstname} {student.lastname}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                Matrícula: {student.matricula}
                                            </div>
                                            {student.campus && (
                                                <div className="text-xs text-gray-400">
                                                    Campus: {student.campus.name}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                        {errors.student_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.student_id}</p>
                        )}
                    </div>
                )}

                {/* Period Selection */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Período *
                    </label>
                    <select
                        name="period_id"
                        value={formData.period_id}
                        onChange={handleInputChange}
                        className={`w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            errors.period_id ? 'border-red-500' : 'border-gray-300'
                        }`}
                    >
                        <option value="">Seleccionar período</option>
                        {periods.map(period => (
                            <option key={period.id} value={period.id}>
                                {period.name} - {formatCurrency(period.price)}
                            </option>
                        ))}
                    </select>
                    {errors.period_id && (
                        <p className="mt-1 text-sm text-red-600">{errors.period_id}</p>
                    )}
                </div>

                {/* Concept */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Concepto *
                    </label>
                    <input
                        type="text"
                        name="concept"
                        value={formData.concept}
                        onChange={handleInputChange}
                        placeholder="Ej: Colegiatura, Inscripción, Material..."
                        className={`w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            errors.concept ? 'border-red-500' : 'border-gray-300'
                        }`}
                    />
                    {errors.concept && (
                        <p className="mt-1 text-sm text-red-600">{errors.concept}</p>
                    )}
                </div>

                {/* Total Amount */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Monto Total *
                    </label>
                    <div className="relative">
                        <span className="absolute left-3 top-2 text-gray-500">$</span>
                        <input
                            type="number"
                            name="total_amount"
                            value={formData.total_amount}
                            onChange={handleInputChange}
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            className={`w-full border rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                errors.total_amount ? 'border-red-500' : 'border-gray-300'
                            }`}
                        />
                    </div>
                    {errors.total_amount && (
                        <p className="mt-1 text-sm text-red-600">{errors.total_amount}</p>
                    )}
                </div>

                {/* Due Date */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Fecha de Vencimiento *
                    </label>
                    <input
                        type="date"
                        name="due_date"
                        value={formData.due_date}
                        onChange={handleInputChange}
                        min={getMinDate()}
                        className={`w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            errors.due_date ? 'border-red-500' : 'border-gray-300'
                        }`}
                    />
                    {errors.due_date && (
                        <p className="mt-1 text-sm text-red-600">{errors.due_date}</p>
                    )}
                </div>

                {/* Description */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Descripción
                    </label>
                    <textarea
                        name="description"
                        value={formData.description}
                        onChange={handleInputChange}
                        rows={3}
                        placeholder="Descripción adicional del adeudo (opcional)..."
                        className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                {/* Submit Button */}
                <div className="flex justify-end space-x-3">
                    {onDebtCreated && (
                        <button
                            type="button"
                            onClick={() => onDebtCreated(null)}
                            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                    )}
                    <button
                        type="submit"
                        disabled={loading}
                        className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center"
                    >
                        {loading && (
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                        )}
                        {loading ? 'Creando...' : 'Crear Adeudo'}
                    </button>
                </div>
            </form>
        </div>
    );
};

export default CreateDebt;