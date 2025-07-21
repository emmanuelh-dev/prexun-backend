import React, { useState, useEffect } from 'react';
import axios from 'axios';

const UserForm = ({ userId = null, onSuccess }) => {
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        role: 'admin',
        suspendido: false,
        campuses: []
    });
    
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [campuses, setCampuses] = useState([]);
    
    useEffect(() => {
        fetchCampuses();
        if (userId) {
            fetchUser();
        }
    }, [userId]);
    
    const fetchCampuses = async () => {
        try {
            const response = await axios.get('/api/campus');
            setCampuses(response.data);
        } catch (error) {
            console.error('Error fetching campuses:', error);
        }
    };
    
    const fetchUser = async () => {
        try {
            setLoading(true);
            const response = await axios.get(`/api/users/${userId}`);
            const user = response.data;
            setFormData({
                name: user.name || '',
                email: user.email || '',
                password: '',
                role: user.role || 'admin',
                suspendido: user.suspendido || false,
                campuses: user.campuses?.map(campus => campus.id) || []
            });
        } catch (error) {
            console.error('Error fetching user:', error);
        } finally {
            setLoading(false);
        }
    };
    
    const handleInputChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
    };
    
    const handleCampusChange = (campusId) => {
        setFormData(prev => ({
            ...prev,
            campuses: prev.campuses.includes(campusId)
                ? prev.campuses.filter(id => id !== campusId)
                : [...prev.campuses, campusId]
        }));
    };
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});
        
        try {
            const url = userId ? `/api/users/${userId}` : '/api/users';
            const method = userId ? 'put' : 'post';
            
            const submitData = { ...formData };
            if (!submitData.password) {
                delete submitData.password;
            }
            
            const response = await axios[method](url, submitData);
            
            if (onSuccess) {
                onSuccess(response.data.user);
            }
            
            if (!userId) {
                setFormData({
                    name: '',
                    email: '',
                    password: '',
                    role: 'admin',
                    suspendido: false,
                    campuses: []
                });
            }
            
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                console.error('Error saving user:', error);
            }
        } finally {
            setLoading(false);
        }
    };
    
    if (loading && userId) {
        return <div className="flex justify-center p-4">Cargando...</div>;
    }
    
    return (
        <form onSubmit={handleSubmit} className="space-y-6 max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
            <h2 className="text-2xl font-bold text-gray-800 mb-6">
                {userId ? 'Editar Usuario' : 'Crear Usuario'}
            </h2>
            
            <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                    Nombre *
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                {errors.name && (
                    <p className="mt-1 text-sm text-red-600">{errors.name[0]}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                    Email *
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                {errors.email && (
                    <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                    Contraseña {!userId && '*'}
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    value={formData.password}
                    onChange={handleInputChange}
                    required={!userId}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                {errors.password && (
                    <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>
                )}
            </div>
            
            <div>
                <label htmlFor="role" className="block text-sm font-medium text-gray-700 mb-2">
                    Rol *
                </label>
                <select
                    id="role"
                    name="role"
                    value={formData.role}
                    onChange={handleInputChange}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="admin">Administrador</option>
                    <option value="teacher">Maestro</option>
                    <option value="super_admin">Super Administrador</option>
                </select>
                {errors.role && (
                    <p className="mt-1 text-sm text-red-600">{errors.role[0]}</p>
                )}
            </div>
            
            {/* Campo Suspendido - Checkbox */}
            <div className="flex items-center">
                <input
                    type="checkbox"
                    id="suspendido"
                    name="suspendido"
                    checked={formData.suspendido}
                    onChange={handleInputChange}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label htmlFor="suspendido" className="ml-2 block text-sm text-gray-700">
                    Usuario suspendido
                </label>
            </div>
            {errors.suspendido && (
                <p className="mt-1 text-sm text-red-600">{errors.suspendido[0]}</p>
            )}
            
            {/* Campus Selection */}
            {campuses.length > 0 && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Campus
                    </label>
                    <div className="space-y-2 max-h-32 overflow-y-auto border border-gray-300 rounded-md p-2">
                        {campuses.map(campus => (
                            <div key={campus.id} className="flex items-center">
                                <input
                                    type="checkbox"
                                    id={`campus-${campus.id}`}
                                    checked={formData.campuses.includes(campus.id)}
                                    onChange={() => handleCampusChange(campus.id)}
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <label htmlFor={`campus-${campus.id}`} className="ml-2 block text-sm text-gray-700">
                                    {campus.name}
                                </label>
                            </div>
                        ))}
                    </div>
                    {errors.campuses && (
                        <p className="mt-1 text-sm text-red-600">{errors.campuses[0]}</p>
                    )}
                </div>
            )}
            
            <button
                type="submit"
                disabled={loading}
                className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                {loading ? 'Guardando...' : (userId ? 'Actualizar Usuario' : 'Crear Usuario')}
            </button>
        </form>
    );
};

export default UserForm;