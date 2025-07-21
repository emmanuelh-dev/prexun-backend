import React, { useState, useEffect } from 'react';
import axios from 'axios';
import UserForm from './UserForm';

const UserList = () => {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [editingUser, setEditingUser] = useState(null);
    const [showForm, setShowForm] = useState(false);
    const [error, setError] = useState(null);
    
    useEffect(() => {
        fetchUsers();
    }, []);
    
    const fetchUsers = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/users');
            setUsers(response.data);
            setError(null);
        } catch (error) {
            console.error('Error fetching users:', error);
            setError('Error al cargar los usuarios');
        } finally {
            setLoading(false);
        }
    };
    
    const handleToggleSuspension = async (userId, currentStatus) => {
        try {
            await axios.put(`/api/users/${userId}`, {
                suspendido: !currentStatus
            });
            
            // Actualizar el estado local
            setUsers(prevUsers => 
                prevUsers.map(user => 
                    user.id === userId 
                        ? { ...user, suspendido: !currentStatus }
                        : user
                )
            );
        } catch (error) {
            console.error('Error updating user suspension:', error);
            alert('Error al actualizar el estado de suspensión');
        }
    };
    
    const handleDeleteUser = async (userId) => {
        if (!confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
            return;
        }
        
        try {
            await axios.delete(`/api/users/${userId}`);
            setUsers(prevUsers => prevUsers.filter(user => user.id !== userId));
        } catch (error) {
            console.error('Error deleting user:', error);
            alert('Error al eliminar el usuario');
        }
    };
    
    const handleEditUser = (user) => {
        setEditingUser(user.id);
        setShowForm(true);
    };
    
    const handleFormSuccess = (updatedUser) => {
        if (editingUser) {
            // Actualizar usuario existente
            setUsers(prevUsers => 
                prevUsers.map(user => 
                    user.id === updatedUser.id ? updatedUser : user
                )
            );
        } else {
            // Agregar nuevo usuario
            setUsers(prevUsers => [...prevUsers, updatedUser]);
        }
        
        setShowForm(false);
        setEditingUser(null);
    };
    
    const getRoleLabel = (role) => {
        const roles = {
            'admin': 'Administrador',
            'teacher': 'Maestro',
            'maestro': 'Maestro',
            'super_admin': 'Super Administrador'
        };
        return roles[role] || role;
    };
    
    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="text-lg text-gray-600">Cargando usuarios...</div>
            </div>
        );
    }
    
    if (error) {
        return (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {error}
            </div>
        );
    }
    
    return (
        <div className="container mx-auto px-4 py-8">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-3xl font-bold text-gray-800">Gestión de Usuarios</h1>
                <button
                    onClick={() => {
                        setEditingUser(null);
                        setShowForm(true);
                    }}
                    className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                    Nuevo Usuario
                </button>
            </div>
            
            {showForm && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4 max-h-screen overflow-y-auto">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold">
                                {editingUser ? 'Editar Usuario' : 'Nuevo Usuario'}
                            </h2>
                            <button
                                onClick={() => {
                                    setShowForm(false);
                                    setEditingUser(null);
                                }}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>
                        <UserForm
                            userId={editingUser}
                            onSuccess={handleFormSuccess}
                        />
                    </div>
                </div>
            )}
            
            <div className="bg-white shadow-md rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nombre
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rol
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Campus
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {users.map((user) => (
                            <tr key={user.id} className={user.suspendido ? 'bg-red-50' : ''}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="text-sm font-medium text-gray-900">
                                        {user.name}
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="text-sm text-gray-900">{user.email}</div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {getRoleLabel(user.role)}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="flex items-center">
                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                            user.suspendido 
                                                ? 'bg-red-100 text-red-800' 
                                                : 'bg-green-100 text-green-800'
                                        }`}>
                                            {user.suspendido ? 'Suspendido' : 'Activo'}
                                        </span>
                                        <button
                                            onClick={() => handleToggleSuspension(user.id, user.suspendido)}
                                            className={`ml-2 px-2 py-1 text-xs rounded ${
                                                user.suspendido
                                                    ? 'bg-green-600 text-white hover:bg-green-700'
                                                    : 'bg-red-600 text-white hover:bg-red-700'
                                            } transition-colors`}
                                            title={user.suspendido ? 'Activar usuario' : 'Suspender usuario'}
                                        >
                                            {user.suspendido ? 'Activar' : 'Suspender'}
                                        </button>
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="text-sm text-gray-900">
                                        {user.campuses?.map(campus => campus.name).join(', ') || 'Sin campus'}
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button
                                        onClick={() => handleEditUser(user)}
                                        className="text-blue-600 hover:text-blue-900 mr-3"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        onClick={() => handleDeleteUser(user.id)}
                                        className="text-red-600 hover:text-red-900"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                
                {users.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                        No hay usuarios registrados
                    </div>
                )}
            </div>
        </div>
    );
};

export default UserList;